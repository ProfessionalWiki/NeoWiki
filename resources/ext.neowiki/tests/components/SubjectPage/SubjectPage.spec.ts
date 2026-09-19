import { afterEach, beforeEach, describe, expect, it, vi, type MockInstance } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, shallowMount, VueWrapper } from '@vue/test-utils';
import { CdxMenuButton } from '@wikimedia/codex';
import SubjectPage from '@/components/SubjectPage/SubjectPage.vue';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import DataExportButton from '@/components/SubjectsManager/DataExportButton.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { CdxDialogStub, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@/domain/Schema.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { newRelation, RelationValue } from '@/domain/Value.ts';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import type { ReferencingSubjects, SubjectWithReferencedSubjects } from '@/domain/SubjectRepository.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { SubjectNotFoundError } from '@/persistence/SubjectNotFoundError.ts';
import { Service } from '@/NeoWikiServices.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';

const SUBJECT_ID = 's1aaaaaaaaaaaa1';
const REFERENCED_ID = 's1bbbbbbbbbbbb1';
const OTHER_REFERENCED_ID = 's1ccccccccccc11';

// The referenced Subject lives on its own page, so every assertion about "the page the Subject is
// stored on" fails if the page is taken from the wrong Subject.
const PAGE_ID = 42;
const PAGE_NAME = 'ACME Inc';
const REFERENCED_PAGE_ID = 77;
const REFERENCED_PAGE_NAME = 'Anvil (product)';

const PROJECTIONS = [ 'native', 'EDM' ];
const IRI_BASE = 'https://data.example.org/entity/';

const REQUESTED_ROW = '.ext-neowiki-subject-page__subject .ext-neowiki-subject-row';
const REFERENCED_ROW = '.ext-neowiki-subject-page__referenced .ext-neowiki-subject-row';
const REFERENCING_ROW = '.ext-neowiki-subject-page__referencing .ext-neowiki-subject-row';
const ROW_CAPTION = '.ext-neowiki-subject-row__caption';
const ROW_LABEL = '.ext-neowiki-subject-row__label';
const EDIT_CONTROL = '[aria-label="neowiki-managesubjects-row-edit"]';
const DELETE_CONTROL = '[aria-label="neowiki-managesubjects-row-delete"]';
const COPY_LINK_CONTROL = '[aria-label="neowiki-managesubjects-row-copy-link"]';
const PAGE_LINK = '.ext-neowiki-subject-row__page-value a';
const NAME_LINK = 'a.ext-neowiki-subject-row__name';

const SCHEMAS: Record<string, Schema> = {
	Company: newSchema( { title: 'Company', description: 'An organisation' } ),
	Product: newSchema( { title: 'Product', description: 'Something sold' } ),
};

interface SubjectOptions {
	id: string;
	label: string | null;
	/** Nobody named it, so it is shown under its Schema name. */
	unnamed?: boolean;
	schemaName?: string;
	pageId?: number;
	pageName?: string;
	/** Gives it one relation statement, pointing at this Subject id. */
	relatesTo?: string;
}

function subject( options: SubjectOptions ): SubjectWithContext {
	return newSubject( {
		id: options.id,
		label: options.label,
		displayNameIsGenerated: options.unnamed ?? false,
		schemaName: options.schemaName ?? 'Company',
		statements: options.relatesTo === undefined ? undefined : relationTo( options.relatesTo ),
		pageIdentifiers: new PageIdentifiers( options.pageId ?? PAGE_ID, options.pageName ?? PAGE_NAME ),
	} );
}

function relationTo( targetId: string ): StatementList {
	return new StatementList( [
		new Statement(
			new PropertyName( 'Made in' ),
			RelationType.typeName,
			new RelationValue( [ newRelation( undefined, targetId ) ] ),
		),
	] );
}

const requestedSubject = subject( { id: SUBJECT_ID, label: 'ACME Inc' } );
const referencedSubject = subject( {
	id: REFERENCED_ID,
	label: 'Anvil',
	schemaName: 'Product',
	pageId: REFERENCED_PAGE_ID,
	pageName: REFERENCED_PAGE_NAME,
} );

const getSubjectWithReferencedSubjectsMock = vi.fn();
const getReferencingSubjectsMock = vi.fn();
const getSubjectForEditingMock = vi.fn();
const getPageSubjectsMock = vi.fn();
const getSchemaMock = vi.fn();
const checkPermissionsMock = vi.fn();

const canEditSubjectRef = ref( false );
const canDeleteSubjectRef = ref( false );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateMainSubject: ref( false ),
		canCreateOtherSubject: ref( false ),
		canEditSubject: canEditSubjectRef,
		canDeleteSubject: canDeleteSubjectRef,
		checkPermissions: checkPermissionsMock,
	} ),
} ) );

function bundle(
	referencedSubjects: Subject[] = [],
	requested: Subject = requestedSubject,
): SubjectWithReferencedSubjects {
	return { requestedSubject: requested, referencedSubjects };
}

function referencing( subjects: Subject[], propertyNames = [ 'Made in' ], truncated = false ): ReferencingSubjects {
	return {
		subjects: subjects.map( ( each ) => ( { subject: each, propertyNames } ) ),
		truncated,
	};
}

/**
 * Stands in for the singleton the Pinia stores read through, which is not the injected repository
 * the page itself reads through.
 */
function stubExtension( repositories: { subject?: object; schema?: object } ): void {
	vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( {
		getSubjectRepository: () => repositories.subject ?? {},
		getSchemaRepository: () => repositories.schema ?? {},
	} as unknown as NeoWikiExtension );
}

// Renders its slot, so the Subject name the delete confirmation interpolates reaches the DOM.
const I18nSlotStub = {
	template: '<span>{{ messageKey }}<slot /></span>',
	props: [ 'messageKey' ],
};

function silenceConsoleErrors(): MockInstance {
	return vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
}

function mountPage( subjectFirst = false ): VueWrapper {
	setupMwMock( {
		functions: [ 'config', 'msg', 'message', 'notify', 'util' ],
		// Core's own separator, which the row captions join property names with.
		messages: { 'comma-separator': ', ' },
		config: {
			wgNeoWikiRdfProjections: PROJECTIONS,
			wgNeoWikiSubjectIriBase: IRI_BASE,
			wgNeoWikiSubjectFirst: subjectFirst,
		},
	} );

	const pinia = createPinia();
	setActivePinia( pinia );

	return shallowMount( SubjectPage, {
		props: { subjectId: SUBJECT_ID },
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			// The row and the delete dialog are this page's own building blocks rather than
			// collaborators to stand in for: the assertions below are about what they render.
			stubs: {
				CdxIcon: true,
				CdxDialog: CdxDialogStub,
				I18nSlot: I18nSlotStub,
				SubjectRow: false,
				SubjectDeleteDialog: false,
			},
			provide: {
				[ Service.SubjectRepository ]: {
					getSubjectWithReferencedSubjects: getSubjectWithReferencedSubjectsMock,
					getReferencingSubjects: getReferencingSubjectsMock,
					getSubjectForEditing: getSubjectForEditingMock,
					getPageSubjects: getPageSubjectsMock,
				},
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
			},
		},
	} );
}

async function mountLoadedPage( subjectFirst = false ): Promise<VueWrapper> {
	const wrapper = mountPage( subjectFirst );

	// onMounted awaits the Subject read, then the Schema reads, then the permission check.
	await flushPromises();

	return wrapper;
}

function isOpen( row: ReturnType<VueWrapper['find']> ): boolean {
	return ( row.find( 'details' ).element as HTMLDetailsElement ).open;
}

async function openEditorOn( control: ReturnType<VueWrapper['find']> ): Promise<void> {
	await control.trigger( 'click' );
	await flushPromises();
}

async function confirmDelete( wrapper: VueWrapper, control: ReturnType<VueWrapper['find']> ): Promise<void> {
	await control.trigger( 'click' );
	// The click first settles where the deletion goes, which on a subject-first wiki is a read.
	await flushPromises();
	wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'no longer needed' );
	await flushPromises();
}

describe( 'SubjectPage', () => {

	beforeEach( () => {
		getSubjectWithReferencedSubjectsMock.mockReset().mockResolvedValue( bundle() );
		getReferencingSubjectsMock.mockReset().mockResolvedValue( referencing( [] ) );
		getSchemaMock.mockReset().mockImplementation( ( name: string ) =>
			SCHEMAS[ name ] === undefined ?
				Promise.reject( new Error( `Unknown schema: ${ name }` ) ) :
				Promise.resolve( SCHEMAS[ name ] ) );
		getSubjectForEditingMock.mockReset();
		getPageSubjectsMock.mockReset().mockResolvedValue( {
			pageSubjects: new PageSubjects( PAGE_ID, new SubjectId( SUBJECT_ID ), [ requestedSubject ] ),
			referencedSubjects: [],
			schemas: [],
		} );
		checkPermissionsMock.mockReset().mockResolvedValue( undefined );
	} );

	afterEach( () => {
		canEditSubjectRef.value = false;
		canDeleteSubjectRef.value = false;
		vi.restoreAllMocks();
	} );

	it( 'reads the Subject its subjectId names', async () => {
		await mountLoadedPage();

		expect( getSubjectWithReferencedSubjectsMock )
			.toHaveBeenCalledWith( expect.objectContaining( { text: SUBJECT_ID } ) );
	} );

	it( 'shows the loading placeholder until the read lands', () => {
		// Deliberately never settled: the assertion is about the window before the read lands.
		let land!: ( value: unknown ) => void;
		getSubjectWithReferencedSubjectsMock.mockReturnValue( new Promise( ( resolve ) => {
			land = resolve;
		} ) );

		const wrapper = mountPage();

		expect( wrapper.find( '.ext-neowiki-subject-page__loading' ).exists() ).toBe( true );
		expect( wrapper.find( REQUESTED_ROW ).exists() ).toBe( false );

		land( bundle() );
	} );

	it( 'names the Subject and shows the Schema it instantiates', async () => {
		const wrapper = await mountLoadedPage();

		expect( wrapper.find( `${ REQUESTED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'ACME Inc' );
		expect( wrapper.findComponent( SchemaNameDisplay ).props( 'schemaName' ) ).toBe( 'Company' );
	} );

	// The page is about one Subject, so it opens on that one's data rather than on everything at once.
	it( 'opens on the requested Subject with the ones it references collapsed', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		expect( isOpen( wrapper.find( REQUESTED_ROW ) ) ).toBe( true );
		expect( isOpen( wrapper.find( REFERENCED_ROW ) ) ).toBe( false );
	} );

	it( 'collapses and re-expands a row from its header', async () => {
		const wrapper = await mountLoadedPage();
		const header = wrapper.find( '.ext-neowiki-subject-row__header' );

		await header.trigger( 'click' );
		expect( isOpen( wrapper.find( REQUESTED_ROW ) ) ).toBe( false );

		await header.trigger( 'click' );
		expect( isOpen( wrapper.find( REQUESTED_ROW ) ) ).toBe( true );
	} );

	it( 'renders the statements of the Subject and of each Subject it references', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		const subjects = wrapper.findAllComponents( SubjectStatementsView ).map( ( view ) => view.props( 'subject' ) );
		expect( subjects ).toStrictEqual( [ requestedSubject, referencedSubject ] );
	} );

	// Every row here names a Subject stored somewhere else, so each footer links its own page.
	it( 'links each row\'s own hosting page from its footer', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		const requested = wrapper.find( `${ REQUESTED_ROW } ${ PAGE_LINK }` );
		expect( requested.text() ).toBe( PAGE_NAME );
		expect( requested.attributes( 'href' ) ).toBe( '/wiki/' + PAGE_NAME );

		const referenced = wrapper.find( `${ REFERENCED_ROW } ${ PAGE_LINK }` );
		expect( referenced.text() ).toBe( REFERENCED_PAGE_NAME );
		expect( referenced.attributes( 'href' ) ).toBe( '/wiki/' + REFERENCED_PAGE_NAME );
	} );

	// The read omits both page fields for a Subject whose hosting page it could not resolve, and the
	// deserializer puts them into PageIdentifiers unread, so the values decide rather than the type.
	describe( 'a Subject served without a hosting page', () => {

		const pagelessSubject = new SubjectWithContext(
			new SubjectId( SUBJECT_ID ),
			'Homeless',
			'Homeless',
			false,
			'Company',
			new StatementList( [] ),
			new PageIdentifiers( undefined as unknown as number, undefined as unknown as string ),
		);

		beforeEach( () => {
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [], pagelessSubject ) );
		} );

		it( 'still shows the Subject, naming no page', async () => {
			const wrapper = await mountLoadedPage();

			expect( wrapper.find( ROW_LABEL ).text() ).toBe( 'Homeless' );
			expect( wrapper.find( PAGE_LINK ).exists() ).toBe( false );
		} );

		// Editing rights are the hosting page's, so without one there is nothing to ask about — and
		// nothing grants editing, since the permission check is the only thing that ever does.
		it( 'asks about no page, leaving the edit controls ungranted', async () => {
			const wrapper = await mountLoadedPage();

			expect( checkPermissionsMock ).not.toHaveBeenCalled();
			expect( wrapper.find( EDIT_CONTROL ).exists() ).toBe( false );
		} );

	} );

	it( 'checks edit permission against the page the Subject is stored on', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		await mountLoadedPage();

		expect( checkPermissionsMock ).toHaveBeenCalledWith( PAGE_ID );
	} );

	it( 'offers each referenced Subject its own page, and the requested one nothing', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		expect( wrapper.find( `${ REFERENCED_ROW } ${ NAME_LINK }` ).attributes( 'href' ) )
			.toBe( '/wiki/Special:Subject/' + REFERENCED_ID );
		expect( wrapper.find( `${ REQUESTED_ROW } ${ NAME_LINK }` ).exists() ).toBe( false );
	} );

	it( 'lists referenced Subjects in the order the read returned them', async () => {
		const otherReferenced = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ otherReferenced, referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		const names = wrapper.findAll( `${ REFERENCED_ROW } ${ ROW_LABEL }` ).map( ( link ) => link.text() );
		expect( names ).toEqual( [ 'Rocket', 'Anvil' ] );
	} );

	// The header still toggles where it is not a link, as on the Data tab; the statement count stands
	// for the rest of it.
	it( 'opens a referenced row from its header', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		await wrapper.find( `${ REFERENCED_ROW } .ext-neowiki-subject-row__count` ).trigger( 'click' );

		expect( isOpen( wrapper.find( REFERENCED_ROW ) ) ).toBe( true );
	} );

	// The reverse direction: without it the page is a dead end, since nothing on the Subject names
	// the Subjects that point at it.
	describe( 'the Subjects that reference this one', () => {

		const referrer = subject( {
			id: OTHER_REFERENCED_ID,
			label: 'Rocket',
			schemaName: 'Product',
			pageId: 91,
			pageName: 'Rocket (product)',
			relatesTo: SUBJECT_ID,
		} );

		it( 'reads them for the Subject its subjectId names', async () => {
			await mountLoadedPage();

			expect( getReferencingSubjectsMock )
				.toHaveBeenCalledWith( expect.objectContaining( { text: SUBJECT_ID } ) );
		} );

		it( 'lists them in the order the read returned them, collapsed', async () => {
			getReferencingSubjectsMock.mockResolvedValue( referencing( [ referrer, referencedSubject ] ) );

			const wrapper = await mountLoadedPage();

			expect( wrapper.findAll( `${ REFERENCING_ROW } ${ ROW_LABEL }` ).map( ( row ) => row.text() ) )
				.toEqual( [ 'Rocket', 'Anvil' ] );
			expect( isOpen( wrapper.find( REFERENCING_ROW ) ) ).toBe( false );
		} );

		// Which property points here is the whole reason the row is in the list.
		it( 'names the properties through which each one points here', async () => {
			getReferencingSubjectsMock.mockResolvedValue(
				referencing( [ referrer ], [ 'Made in', 'Sold in' ] ) );

			const wrapper = await mountLoadedPage();

			expect( wrapper.find( `${ REFERENCING_ROW } ${ ROW_CAPTION }` ).text() )
				.toBe( 'neowiki-special-subject-referenced-by-propertiesMade in, Sold in' );
		} );

		it( 'shows no section for a Subject nothing references', async () => {
			const wrapper = await mountLoadedPage();

			expect( wrapper.find( '.ext-neowiki-subject-page__referencing-heading' ).exists() ).toBe( false );
			expect( wrapper.find( REFERENCING_ROW ).exists() ).toBe( false );
		} );

		// A wiki with no Neo4j store answers nothing here, and a read can fail on its own. Either way
		// the Subject the reader asked for is what the page is about.
		it( 'shows the Subject anyway when the read fails', async () => {
			const logged = silenceConsoleErrors();
			getReferencingSubjectsMock.mockRejectedValue( new Error( 'Backend down' ) );

			const wrapper = await mountLoadedPage();

			// The failure is caught here rather than left to escape: the page is shown from the
			// read that landed, and nothing downstream of this one sees the rejection at all.
			expect( logged ).toHaveBeenCalledWith(
				'Failed to load referencing subjects:', expect.any( Error ) );
			expect( wrapper.find( `${ REQUESTED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'ACME Inc' );
			expect( wrapper.find( '.ext-neowiki-subject-page__error' ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-subject-page__referencing-heading' ).exists() ).toBe( false );
		} );

		// The section is worth waiting for; the Subject the reader asked for is not worth delaying.
		it( 'shows the Subject while the read of them is still in flight', async () => {
			let land!: ( value: ReferencingSubjects ) => void;
			getReferencingSubjectsMock.mockReturnValue( new Promise( ( resolve ) => {
				land = resolve;
			} ) );

			const wrapper = await mountLoadedPage();

			expect( wrapper.find( `${ REQUESTED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'ACME Inc' );
			expect( wrapper.find( REFERENCING_ROW ).exists() ).toBe( false );

			land( referencing( [ referrer ] ) );
			await flushPromises();

			expect( wrapper.find( `${ REFERENCING_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'Rocket' );
		} );

		// A row the reader opened stays open across the re-read after a write, and that write can have
		// pointed it at a Subject the registry does not hold: without re-seeding, the row shows the
		// new target's bare id.
		it( 'seeds a new target of a row left open through a re-read', async () => {
			const newTargetId = 's1ddddddddddd11';
			const getSubjectMock = vi.fn().mockResolvedValue(
				subject( { id: newTargetId, label: 'Sheffield', schemaName: 'Product' } ) );
			stubExtension( { subject: { getSubject: getSubjectMock } } );
			canDeleteSubjectRef.value = true;
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );
			const rocketRelatingTo = ( targetId: string ): ReferencingSubjects => referencing( [
				subject( {
					id: OTHER_REFERENCED_ID,
					label: 'Rocket',
					schemaName: 'Product',
					relatesTo: targetId,
				} ),
			] );
			getReferencingSubjectsMock.mockResolvedValue( rocketRelatingTo( REFERENCED_ID ) );

			const wrapper = await mountLoadedPage();
			await wrapper.find( `${ REFERENCING_ROW } .ext-neowiki-subject-row__count` ).trigger( 'click' );
			await flushPromises();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getSubjectMock.mockClear();
			getReferencingSubjectsMock.mockResolvedValue( rocketRelatingTo( newTargetId ) );

			// Any write re-reads the page; deleting a referenced Subject is the shortest way there.
			await confirmDelete( wrapper, wrapper.find( `${ REFERENCED_ROW } ${ DELETE_CONTROL }` ) );

			expect( getSubjectMock ).toHaveBeenCalledWith(
				expect.objectContaining( { text: newTargetId } ) );
		} );

		// A subject-first wiki's page exists to hold its Subject, so the last one leaving takes the
		// page with it, and MediaWiki's own form is what confirms that and checks the right.
		it( 'leaves for the page\'s delete form when the Subject is its page\'s only one', async () => {
			canDeleteSubjectRef.value = true;
			const deleteSubject = vi.fn().mockResolvedValue( undefined );
			const wrapper = await mountLoadedPage( true );
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockImplementation( deleteSubject );
			// Restored by re-stubbing rather than vi.unstubAllGlobals(), which would take the `mw`
			// stub with it, out from under every component this file leaves mounted.
			const realLocation = window.location;
			vi.stubGlobal( 'location', { href: '' } );

			try {
				await wrapper.find( `${ REQUESTED_ROW } ${ DELETE_CONTROL }` ).trigger( 'click' );
				await flushPromises();

				expect( location.href ).toBe( '/wiki/ACME Inc?action=delete' );
			} finally {
				vi.stubGlobal( 'location', realLocation );
			}

			expect( wrapper.findComponent( SummaryAction ).exists() ).toBe( false );
			expect( deleteSubject ).not.toHaveBeenCalled();
		} );

		it( 'deletes the Subject alone where its page holds others, which outlive it', async () => {
			canDeleteSubjectRef.value = true;
			getPageSubjectsMock.mockResolvedValue( {
				pageSubjects: new PageSubjects(
					PAGE_ID,
					new SubjectId( SUBJECT_ID ),
					[ requestedSubject, subject( { id: OTHER_REFERENCED_ID, label: 'Rocket' } ) ],
				),
				referencedSubjects: [],
				schemas: [],
			} );
			const deleteSubject = vi.fn().mockResolvedValue( undefined );
			const wrapper = await mountLoadedPage( true );
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockImplementation( deleteSubject );

			await confirmDelete( wrapper, wrapper.find( `${ REQUESTED_ROW } ${ DELETE_CONTROL }` ) );

			expect( deleteSubject ).toHaveBeenCalledWith(
				expect.objectContaining( { text: SUBJECT_ID } ),
				'no longer needed',
			);
		} );

		// The Edit and Delete controls on the page's own row wait for the read of the Subject to
		// return, and so does the editor dialog after a save. A section that never lands must hold
		// neither: with Neo4j slow or unreachable that wait is the whole request timeout.
		it( 'leaves the page\'s own controls unblocked while the read of them hangs', async () => {
			getReferencingSubjectsMock.mockReturnValue( new Promise( () => {
				// Never lands.
			} ) );

			await mountLoadedPage();

			expect( checkPermissionsMock ).toHaveBeenCalledWith( PAGE_ID );
		} );

		it( 'says when the wiki holds more of them than the list shows', async () => {
			getReferencingSubjectsMock.mockResolvedValue( referencing( [ referrer ], [ 'Made in' ], true ) );

			const wrapper = await mountLoadedPage();

			expect( wrapper.find( '.ext-neowiki-subject-page__referencing-truncated' ).text() )
				.toBe( 'neowiki-special-subject-referenced-by-truncated1' );
		} );

		it( 'says nothing about truncation when the list is the whole of it', async () => {
			getReferencingSubjectsMock.mockResolvedValue( referencing( [ referrer ] ) );

			const wrapper = await mountLoadedPage();

			expect( wrapper.find( '.ext-neowiki-subject-page__referencing-truncated' ).exists() ).toBe( false );
		} );

		// Two Subjects pointing at each other put the same Subject in both lists. Two elements with
		// one id is invalid HTML, and every in-page lookup of that id then finds the wrong row.
		describe( 'a Subject that is in both lists', () => {

			beforeEach( () => {
				getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );
				getReferencingSubjectsMock.mockResolvedValue( referencing( [ referencedSubject ] ) );
			} );

			it( 'gets a row id of its own in each', async () => {
				const wrapper = await mountLoadedPage();

				const ids = wrapper.findAll( '.ext-neowiki-subject-row' ).map( ( row ) => row.attributes( 'id' ) );
				expect( new Set( ids ).size ).toBe( ids.length );
			} );

			it( 'opens in one list without opening in the other', async () => {
				const wrapper = await mountLoadedPage();

				await wrapper.find( `${ REFERENCING_ROW } .ext-neowiki-subject-row__count` ).trigger( 'click' );
				await flushPromises();

				expect( isOpen( wrapper.find( REFERENCING_ROW ) ) ).toBe( true );
				expect( isOpen( wrapper.find( REFERENCED_ROW ) ) ).toBe( false );
			} );

		} );

		// One request per target per referrer, none of which the reader has asked to see: they are
		// fetched when a row is opened, which is when RelationDisplay needs them resolved.
		describe( 'the relation targets of a referencing row', () => {

			const target = subject( { id: REFERENCED_ID, label: 'Anvil', schemaName: 'Product' } );

			beforeEach( () => {
				getReferencingSubjectsMock.mockResolvedValue( referencing( [
					subject( {
						id: OTHER_REFERENCED_ID,
						label: 'Rocket',
						schemaName: 'Product',
						relatesTo: REFERENCED_ID,
					} ),
				] ) );
			} );

			it( 'are not fetched while the row is closed', async () => {
				const getSubjectMock = vi.fn().mockResolvedValue( target );
				stubExtension( { subject: { getSubject: getSubjectMock } } );

				await mountLoadedPage();

				expect( getSubjectMock ).not.toHaveBeenCalled();
			} );

			it( 'are fetched when it is opened', async () => {
				const getSubjectMock = vi.fn().mockResolvedValue( target );
				stubExtension( { subject: { getSubject: getSubjectMock } } );

				const wrapper = await mountLoadedPage();
				await wrapper.find( `${ REFERENCING_ROW } .ext-neowiki-subject-row__count` ).trigger( 'click' );
				await flushPromises();

				expect( getSubjectMock ).toHaveBeenCalledWith(
					expect.objectContaining( { text: REFERENCED_ID } ) );
				expect( useSubjectStore().getSubject( new SubjectId( REFERENCED_ID ) ) ).toStrictEqual( target );
			} );

		} );

	} );

	it( 'shows no referenced section for a Subject that references nothing', async () => {
		const wrapper = await mountLoadedPage();

		expect( wrapper.find( REQUESTED_ROW ).exists() ).toBe( true );
		expect( wrapper.find( '.ext-neowiki-subject-page__referenced-heading' ).exists() ).toBe( false );
	} );

	// RelationDisplay resolves a relation target through the registry, so a target the page did not
	// seed renders as an error rather than as the Subject it names.
	it( 'seeds the subject store with the Subject and everything it references', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		await mountLoadedPage();

		const subjectStore = useSubjectStore();
		expect( subjectStore.getSubject( new SubjectId( SUBJECT_ID ) ) ).toStrictEqual( requestedSubject );
		expect( subjectStore.getSubject( new SubjectId( REFERENCED_ID ) ) ).toStrictEqual( referencedSubject );
	} );

	// The read expands relations one level only, and RelationDisplay resolves a target through the
	// registry, so a referenced Subject's own relation targets render as errors unless they are seeded.
	it( 'seeds the relation targets of the referenced Subjects too', async () => {
		const nested = subject( { id: OTHER_REFERENCED_ID, label: 'Sheffield', schemaName: 'Product' } );
		const getSubjectMock = vi.fn().mockResolvedValue( nested );
		stubExtension( { subject: { getSubject: getSubjectMock } } );
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [
			subject( {
				id: REFERENCED_ID,
				label: 'Anvil',
				schemaName: 'Product',
				relatesTo: OTHER_REFERENCED_ID,
			} ),
		] ) );

		await mountLoadedPage();

		expect( getSubjectMock ).toHaveBeenCalledWith( expect.objectContaining( { text: OTHER_REFERENCED_ID } ) );
		expect( useSubjectStore().getSubject( new SubjectId( OTHER_REFERENCED_ID ) ) ).toStrictEqual( nested );
	} );

	// A relation target can be on a page the reader may not read. RelationDisplay marks the one target
	// it cannot resolve; losing the whole page over it would be worse.
	it( 'renders the page when a relation target cannot be fetched', async () => {
		silenceConsoleErrors();
		stubExtension( { subject: { getSubject: vi.fn().mockRejectedValue( new Error( 'Forbidden' ) ) } } );
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [
			subject( {
				id: REFERENCED_ID,
				label: 'Anvil',
				schemaName: 'Product',
				relatesTo: OTHER_REFERENCED_ID,
			} ),
		] ) );

		const wrapper = await mountLoadedPage();

		expect( wrapper.find( `${ REQUESTED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'ACME Inc' );
		expect( wrapper.find( `${ REFERENCED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'Anvil' );
	} );

	// SubjectStatementsView renders from the Schema store, and the Subject read carries no Schemas.
	it( 'seeds the schema store with the Schema of every Subject shown', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		await mountLoadedPage();

		const schemaStore = useSchemaStore();
		expect( schemaStore.getSchema( 'Company' ) ).toStrictEqual( SCHEMAS.Company );
		expect( schemaStore.getSchema( 'Product' ) ).toStrictEqual( SCHEMAS.Product );
	} );

	it( 'fetches a Schema that several of the Subjects share only once', async () => {
		const otherReferenced = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject, otherReferenced ] ) );

		await mountLoadedPage();

		expect( getSchemaMock.mock.calls.map( ( [ name ] ) => name ).sort() ).toEqual( [ 'Company', 'Product' ] );
	} );

	// A Schema page can be deleted while Subjects still name it; the statements view already renders
	// a Subject whose Schema it does not have.
	it( 'keeps the Schemas that did load when another Schema cannot be loaded', async () => {
		silenceConsoleErrors();
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );
		getSchemaMock.mockImplementation( ( name: string ) =>
			name === 'Company' ?
				Promise.reject( new Error( 'Schema page deleted' ) ) :
				Promise.resolve( SCHEMAS[ name ] ) );

		const wrapper = await mountLoadedPage();

		expect( wrapper.find( ROW_LABEL ).text() ).toBe( 'ACME Inc' );
		expect( useSchemaStore().getSchema( 'Product' ) ).toStrictEqual( SCHEMAS.Product );
	} );

	it( 'reports a Subject the wiki serves none of, naming the id that was asked for', async () => {
		silenceConsoleErrors();
		getSubjectWithReferencedSubjectsMock.mockRejectedValue( new SubjectNotFoundError( SUBJECT_ID ) );

		const wrapper = await mountLoadedPage();

		expect( wrapper.find( '.ext-neowiki-subject-page__error' ).text() )
			.toBe( 'neowiki-special-subject-not-found' + SUBJECT_ID );
		expect( wrapper.find( REQUESTED_ROW ).exists() ).toBe( false );
	} );

	it( 'reports a read that failed for any other reason as a load failure', async () => {
		silenceConsoleErrors();
		getSubjectWithReferencedSubjectsMock.mockRejectedValue( new Error( 'Network down' ) );

		const wrapper = await mountLoadedPage();

		expect( wrapper.find( '.ext-neowiki-subject-page__error' ).text() )
			.toBe( 'neowiki-special-subject-load-error' );
	} );

	it( 'offers a read-only user neither editing nor deletion', async () => {
		const wrapper = await mountLoadedPage();

		expect( wrapper.find( REQUESTED_ROW ).exists() ).toBe( true );
		expect( wrapper.find( EDIT_CONTROL ).exists() ).toBe( false );
		expect( wrapper.find( DELETE_CONTROL ).exists() ).toBe( false );
	} );

	// Adding, moving and the Main Subject are a page's business, and this page is one Subject's. Both
	// action sets are named in full, so a control that reappears fails this rather than going unnoticed.
	describe( 'what an editor is offered', () => {

		const editorActions = [
			'neowiki-managesubjects-row-copy-link',
			'neowiki-managesubjects-row-edit',
			'neowiki-managesubjects-row-delete',
		];

		beforeEach( () => {
			canEditSubjectRef.value = true;
			canDeleteSubjectRef.value = true;
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );
			// A row in each list, so a control that appears on one of them and not the others fails this.
			getReferencingSubjectsMock.mockResolvedValue( referencing( [
				subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } ),
			] ) );
		} );

		it( 'offers each row exactly its own actions', async () => {
			const wrapper = await mountLoadedPage();

			const strips = wrapper.findAll( '.ext-neowiki-subject-row__actions' )
				.map( ( strip ) => strip.findAll( '[aria-label]' ).map( ( control ) => control.attributes( 'aria-label' ) ) );

			expect( strips ).toEqual( [ editorActions, editorActions, editorActions ] );
		} );

		it( 'orders each overflow menu the same way', async () => {
			const wrapper = await mountLoadedPage();

			expect( wrapper.findAllComponents( CdxMenuButton )
				.map( ( menu ) => menu.props( 'menuItems' ).map( ( item ) => item.value ) ) )
				.toEqual( [
					[ 'copy-link', 'edit', 'delete' ],
					[ 'open', 'copy-link', 'edit', 'delete' ],
					[ 'open', 'copy-link', 'edit', 'delete' ],
				] );
		} );

		// The add button is a page's affordance, and its label is button text rather than an aria-label.
		it( 'offers no way to add a Subject', async () => {
			const wrapper = await mountLoadedPage();

			expect( wrapper.text() ).not.toContain( 'neowiki-managesubjects-add-button' );
		} );

	} );

	// The reader is on one Subject's page while every row names another: a fragment of the current
	// URL would point at whichever Subject the page happens to be about.
	it( 'copies a row\'s own Special:Subject URL, absolute', async () => {
		const writeText = vi.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', { value: { writeText }, configurable: true } );
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();
		await wrapper.findAll( COPY_LINK_CONTROL )[ 1 ].trigger( 'click' );
		await flushPromises();

		const copied = new URL( writeText.mock.calls[ 0 ][ 0 ] as string );
		expect( copied.pathname ).toBe( '/wiki/Special:Subject/' + REFERENCED_ID );
		expect( copied.origin ).toBe( location.origin );
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-link-copied', { type: 'success' } );
	} );

	it( 'reports a refused clipboard write instead of claiming the link was copied', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const writeText = vi.fn().mockRejectedValue( new Error( 'clipboard denied' ) );
		Object.defineProperty( navigator, 'clipboard', { value: { writeText }, configurable: true } );

		const wrapper = await mountLoadedPage();
		await wrapper.find( COPY_LINK_CONTROL ).trigger( 'click' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-link-copy-error', { type: 'error' } );
	} );

	it( 'shows the wiki\'s export projections and each Subject\'s own IRI', async () => {
		getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

		const wrapper = await mountLoadedPage();

		expect( wrapper.findComponent( DataExportButton ).props( 'projections' ) ).toEqual( PROJECTIONS );
		const iris = wrapper.findAll( '.ext-neowiki-subject-row__iri-value data' )
			.map( ( element ) => element.attributes( 'value' ) );
		expect( iris ).toEqual( [ IRI_BASE + SUBJECT_ID, IRI_BASE + REFERENCED_ID ] );
	} );

	describe( 'edit flow', () => {

		// Values neither the page's own read nor its Schema store produced, so an assertion on them
		// passes only if the dialog was handed what the repositories returned.
		const freshSubject = subject( { id: SUBJECT_ID, label: 'Fetched label' } );
		const freshSchema = newSchema( { title: 'Company', description: 'Freshly fetched' } );

		beforeEach( () => {
			canEditSubjectRef.value = true;
			getSubjectForEditingMock.mockResolvedValue( freshSubject );
		} );

		it( 'opens the editor on the Subject and Schema fetched from the repositories', async () => {
			const wrapper = await mountLoadedPage();
			getSchemaMock.mockResolvedValue( freshSchema );

			await openEditorOn( wrapper.find( EDIT_CONTROL ) );

			expect( getSubjectForEditingMock ).toHaveBeenCalledWith( expect.objectContaining( { text: SUBJECT_ID } ) );
			const dialog = wrapper.findComponent( SubjectEditorDialog );
			expect( dialog.props( 'open' ) ).toBe( true );
			expect( dialog.props( 'subject' ) ).toStrictEqual( freshSubject );
			expect( dialog.props( 'schema' ) ).toStrictEqual( freshSchema );
		} );

		it( 'opens the editor on the referenced Subject whose row asked for it', async () => {
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );

			const wrapper = await mountLoadedPage();
			await openEditorOn( wrapper.findAll( EDIT_CONTROL )[ 1 ] );

			expect( getSubjectForEditingMock ).toHaveBeenCalledWith( expect.objectContaining( { text: REFERENCED_ID } ) );
			expect( getSchemaMock ).toHaveBeenCalledWith( 'Product' );
		} );

		it( 'reports a failed fetch instead of opening the editor', async () => {
			getSubjectForEditingMock.mockRejectedValue( new Error( 'Unknown subject' ) );

			const wrapper = await mountLoadedPage();
			await openEditorOn( wrapper.find( EDIT_CONTROL ) );

			expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( false );
			expect( mw.notify ).toHaveBeenCalledWith( 'Unknown subject', { type: 'error' } );
		} );

		// A save can add or drop relations, so the referenced Subjects the page shows are re-read
		// rather than left as the ones it was built from.
		it( 'saves through the store and re-reads', async () => {
			const wrapper = await mountLoadedPage();
			const updateSubject = vi.spyOn( useSubjectStore(), 'updateSubject' ).mockResolvedValue( undefined );
			await openEditorOn( wrapper.find( EDIT_CONTROL ) );
			getSubjectWithReferencedSubjectsMock.mockClear();

			await wrapper.findComponent( SubjectEditorDialog ).props( 'onSave' )( freshSubject, 'a comment' );
			await flushPromises();

			expect( updateSubject ).toHaveBeenCalledWith( freshSubject, 'a comment' );
			expect( getSubjectWithReferencedSubjectsMock ).toHaveBeenCalledTimes( 1 );
		} );

		// A Subject the editor created is always followed by the save that points at it, and that
		// save re-reads; doing it here too would read the page twice for one edit.
		it( 'creates a Subject the editor added without re-reading', async () => {
			const created = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );

			const wrapper = await mountLoadedPage();
			const createSubject = vi.spyOn( useSubjectStore(), 'createSubject' )
				.mockResolvedValue( created.getId() );
			await openEditorOn( wrapper.find( EDIT_CONTROL ) );
			getSubjectWithReferencedSubjectsMock.mockClear();

			const onCreate = wrapper.findComponent( SubjectEditorDialog ).props( 'onCreate' );
			await onCreate!( created, REFERENCED_PAGE_ID, 'a comment' );
			await flushPromises();

			expect( createSubject ).toHaveBeenCalledWith( created, REFERENCED_PAGE_ID, 'a comment' );
			expect( getSubjectWithReferencedSubjectsMock ).not.toHaveBeenCalled();
		} );

		// The write committed whatever the re-read does, so telling the reader the Subject is gone
		// would be a lie about their own save.
		it( 'keeps the saved Subject on screen when the re-read after a save fails', async () => {
			silenceConsoleErrors();

			const wrapper = await mountLoadedPage();
			vi.spyOn( useSubjectStore(), 'updateSubject' ).mockResolvedValue( undefined );
			await openEditorOn( wrapper.find( EDIT_CONTROL ) );
			getSubjectWithReferencedSubjectsMock.mockRejectedValue( new SubjectNotFoundError( SUBJECT_ID ) );

			await wrapper.findComponent( SubjectEditorDialog ).props( 'onSave' )( freshSubject, 'a comment' );
			await flushPromises();

			expect( wrapper.find( `${ REQUESTED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'ACME Inc' );
			expect( wrapper.find( '.ext-neowiki-subject-page__error' ).exists() ).toBe( false );
			expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-special-subject-load-error', { type: 'error' } );
		} );

		it( 'saves a Schema the editor changed', async () => {
			const wrapper = await mountLoadedPage();
			const saveSchema = vi.spyOn( useSchemaStore(), 'saveSchema' ).mockResolvedValue( undefined );
			await openEditorOn( wrapper.find( EDIT_CONTROL ) );

			await wrapper.findComponent( SubjectEditorDialog ).props( 'onSaveSchema' )( freshSchema, 'a comment' );

			expect( saveSchema ).toHaveBeenCalledWith( freshSchema, 'a comment' );
		} );

	} );

	describe( 'delete flow', () => {

		beforeEach( () => {
			canDeleteSubjectRef.value = true;
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject ] ) );
		} );

		it( 'deletes through the store and re-reads, since the relation to it is gone', async () => {
			const wrapper = await mountLoadedPage();
			const deleteSubject = vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getSubjectWithReferencedSubjectsMock.mockClear().mockResolvedValue( bundle() );

			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 1 ] );

			expect( deleteSubject ).toHaveBeenCalledWith(
				expect.objectContaining( { text: REFERENCED_ID } ),
				'no longer needed',
			);
			expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-delete-success', { type: 'success' } );
			expect( getSubjectWithReferencedSubjectsMock ).toHaveBeenCalledTimes( 1 );
			expect( wrapper.find( REFERENCED_ROW ).exists() ).toBe( false );
		} );

		// A deleted referrer stops referring, so the list it was in is re-read like the other one.
		it( 'drops a deleted Subject from the list of those referencing this one', async () => {
			const referrer = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );
			getReferencingSubjectsMock.mockResolvedValue( referencing( [ referrer ] ) );

			const wrapper = await mountLoadedPage();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getReferencingSubjectsMock.mockClear().mockResolvedValue( referencing( [] ) );

			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 2 ] );

			expect( getReferencingSubjectsMock ).toHaveBeenCalledTimes( 1 );
			expect( wrapper.find( REFERENCING_ROW ).exists() ).toBe( false );
		} );

		// The rows the page holds were right when they were read, and a read that fails says nothing
		// about them: emptying the section over one transient failure loses what is still true.
		it( 'keeps the Subjects referencing this one when the re-read of them fails', async () => {
			const logged = silenceConsoleErrors();
			const referrer = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );
			getReferencingSubjectsMock.mockResolvedValue( referencing( [ referrer ] ) );

			const wrapper = await mountLoadedPage();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getReferencingSubjectsMock.mockRejectedValue( new Error( 'Backend down' ) );

			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 1 ] );

			expect( logged ).toHaveBeenCalledWith(
				'Failed to load referencing subjects:', expect.any( Error ) );
			expect( wrapper.find( `${ REFERENCING_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'Rocket' );
		} );

		// Deleting drops the Subject from the store's registry, whose getter throws for an id it no
		// longer holds: rendering the page off that getter is what crashed here.
		it( 'reports the Subject the page is about as gone once it is deleted', async () => {
			const wrapper = await mountLoadedPage();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getSubjectWithReferencedSubjectsMock.mockClear();

			await confirmDelete( wrapper, wrapper.find( DELETE_CONTROL ) );

			expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-delete-success', { type: 'success' } );
			expect( wrapper.find( '.ext-neowiki-subject-page__error' ).text() )
				.toBe( 'neowiki-special-subject-not-found' + SUBJECT_ID );
			expect( wrapper.find( REQUESTED_ROW ).exists() ).toBe( false );
			// Nothing left to re-read for.
			expect( getSubjectWithReferencedSubjectsMock ).not.toHaveBeenCalled();
		} );

		it( 'falls back to the default summary when the user typed none', async () => {
			const wrapper = await mountLoadedPage();
			const deleteSubject = vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle() );

			await wrapper.findAll( DELETE_CONTROL )[ 1 ].trigger( 'click' );
			wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( deleteSubject ).toHaveBeenCalledWith(
				expect.anything(),
				'neowiki-managesubjects-delete-summary-default',
			);
		} );

		it( 'keeps the Subject on screen when the delete fails', async () => {
			silenceConsoleErrors();

			const wrapper = await mountLoadedPage();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockRejectedValue( new Error( 'boom' ) );

			await confirmDelete( wrapper, wrapper.find( DELETE_CONTROL ) );

			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-managesubjects-delete-errorACME Inc',
				{ type: 'error' },
			);
			expect( wrapper.find( `${ REQUESTED_ROW } ${ ROW_LABEL }` ).text() ).toBe( 'ACME Inc' );
		} );

		// The close button, Escape and a click outside all close the dialog through this event.
		it( 'closes the confirmation when the dialog asks to close', async () => {
			const wrapper = await mountLoadedPage();
			await wrapper.findAll( DELETE_CONTROL )[ 1 ].trigger( 'click' );

			wrapper.findComponent( CdxDialogStub ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.find( '.cdx-dialog-stub' ).exists() ).toBe( false );
		} );

		// A referenced row can be stored on another page, so the dialog names the Subject it deletes.
		it( 'names the Subject about to be deleted', async () => {
			const wrapper = await mountLoadedPage();

			await wrapper.findAll( DELETE_CONTROL )[ 1 ].trigger( 'click' );

			expect( wrapper.find( '.cdx-dialog-stub strong' ).text() ).toBe( 'Anvil' );
		} );

		// The delete controls stay live while a re-read is in flight, so a second delete can be
		// acknowledged before the re-read the first one started lands (ADR 30 rule 3).
		it( 'keeps a Subject deleted during a re-read out of the registry', async () => {
			const other = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject, other ] ) );
			const wrapper = await mountLoadedPage();
			const subjectStore = useSubjectStore();
			vi.spyOn( subjectStore, 'deleteSubject' ).mockResolvedValue( undefined );
			let landReRead!: ( value: SubjectWithReferencedSubjects ) => void;
			getSubjectWithReferencedSubjectsMock.mockReturnValueOnce( new Promise( ( resolve ) => {
				landReRead = resolve;
			} ) );

			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 1 ] );
			// The second delete, acknowledged while that re-read is in flight.
			subjectStore.subjects.delete( OTHER_REFERENCED_ID );
			subjectStore.mutationEpoch++;
			landReRead( bundle( [ other ] ) );
			await flushPromises();

			expect( subjectStore.subjects.has( OTHER_REFERENCED_ID ) ).toBe( false );
		} );

		it( 'keeps a Schema read that a Schema change overtook out of the store', async () => {
			const wrapper = await mountLoadedPage();
			const schemaStore = useSchemaStore();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			getSubjectWithReferencedSubjectsMock.mockResolvedValue(
				bundle( [ subject( { id: OTHER_REFERENCED_ID, label: 'Amsterdam', schemaName: 'Place' } ) ] ),
			);
			let landSchema!: ( value: Schema ) => void;
			getSchemaMock.mockReturnValueOnce( new Promise( ( resolve ) => {
				landSchema = resolve;
			} ) );

			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 1 ] );
			// A Schema save, acknowledged while that read is in flight.
			schemaStore.mutationEpoch++;
			landSchema( newSchema( { title: 'Place' } ) );
			await flushPromises();

			expect( schemaStore.schemas.has( 'Place' ) ).toBe( false );
		} );

		it( 'shows what the latest re-read found when an earlier one lands after it', async () => {
			const other = subject( { id: OTHER_REFERENCED_ID, label: 'Rocket', schemaName: 'Product' } );
			getSubjectWithReferencedSubjectsMock.mockResolvedValue( bundle( [ referencedSubject, other ] ) );
			const wrapper = await mountLoadedPage();
			vi.spyOn( useSubjectStore(), 'deleteSubject' ).mockResolvedValue( undefined );
			let landFirstReRead!: ( value: SubjectWithReferencedSubjects ) => void;
			getSubjectWithReferencedSubjectsMock
				.mockReturnValueOnce( new Promise( ( resolve ) => {
					landFirstReRead = resolve;
				} ) )
				.mockResolvedValueOnce( bundle() );

			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 1 ] );
			await confirmDelete( wrapper, wrapper.findAll( DELETE_CONTROL )[ 2 ] );
			landFirstReRead( bundle( [ other ] ) );
			await flushPromises();

			expect( wrapper.find( REFERENCED_ROW ).exists() ).toBe( false );
		} );

	} );

} );
