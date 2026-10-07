import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SchemaCard from '@/components/SchemasPage/SchemaCard.vue';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { createI18nMock, setupMwMock, stubIntersectionObserver } from '../../VueTestHelpers.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import type { SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';

// Each right reaches its ref only through its check, so a page that skips a check offers nothing
// however the fixture is set.
let mayCreateSchemas = false;
let mayEditSchemas = false;
let mayDeleteSchemas = false;
const canCreateSchemasRef = ref( false );
const canEditSchemaRef = ref( false );
const canDeleteSchemaRef = ref( false );

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canCreateSchemas: canCreateSchemasRef,
		canEditSchema: canEditSchemaRef,
		canDeleteSchema: canDeleteSchemaRef,
		checkCreatePermission: vi.fn( async (): Promise<void> => {
			canCreateSchemasRef.value = mayCreateSchemas;
		} ),
		checkEditPermission: vi.fn( async (): Promise<void> => {
			canEditSchemaRef.value = mayEditSchemas;
		} ),
		checkDeletePermission: vi.fn( async (): Promise<void> => {
			canDeleteSchemaRef.value = mayDeleteSchemas;
		} ),
	} ),
} ) );

let mayCreateSubjectPages = false;
const canCreateSubjectPageRef = ref( false );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage: canCreateSubjectPageRef,
		checkCreateSubjectPagePermission: vi.fn( async (): Promise<void> => {
			canCreateSubjectPageRef.value = mayCreateSubjectPages;
		} ),
	} ),
} ) );

const getSchemaMock = vi.fn();
const saveSchemaMock = vi.fn();
let pinia: ReturnType<typeof createPinia>;
let schemaStore: ReturnType<typeof useSchemaStore>;

// The store saves through the extension's repository.
vi.mock( '@/NeoWikiExtension.ts', () => ( {
	NeoWikiExtension: {
		getInstance: () => ( {
			getSchemaRepository: () => ( { saveSchema: saveSchemaMock } ),
		} ),
	},
} ) );

const SchemaCardStub = {
	name: 'SchemaCard',
	template: '<div class="schema-card-stub"></div>',
	props: [ 'summary', 'canEdit', 'canDelete', 'canCreateSubject', 'subjectListAvailable', 'subjectPreviews' ],
	emits: [ 'edit', 'delete', 'create-subject' ],
};

const SchemaCreatorDialogStub = {
	template: '<div></div>',
	props: [ 'open' ],
	emits: [ 'update:open', 'created' ],
};

const SubjectCreatorDialogStub = {
	template: '<div></div>',
	props: [ 'open', 'hostPage', 'initialSchemaName' ],
	emits: [ 'update:open' ],
};

const SchemaEditorDialogStub = {
	template: '<div></div>',
	props: [ 'open', 'initialSchema', 'onSave' ],
	emits: [ 'update:open', 'saved' ],
};

function summaries( names: string[] ): SchemaSummary[] {
	return names.map( ( name ) => ( { name, description: '', propertyCount: 1 } ) );
}

function listSchemas( names: string[] ): void {
	schemaStore.fetchAllSchemaSummaries = vi.fn().mockResolvedValue( summaries( names ) );
}

interface PageOptions {
	subjectListAvailable?: boolean;
	/** Mounts the cards themselves rather than stand-ins, for what only a card shows. */
	realCards?: boolean;
	subjectSummaryLookup?: SubjectSummaryLookup;
}

function mountPage( options: PageOptions = {} ): VueWrapper {
	setupMwMock( {
		functions: [ 'config', 'msg', 'util', 'message', 'notify' ],
		config: { wgNeoWikiSubjectListAvailable: options.subjectListAvailable ?? true },
	} );

	return mount( SchemasPage, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
				[ Service.SubjectSummaryLookup ]: options.subjectSummaryLookup ?? { getSubjectSummaries: vi.fn() },
			},
			stubs: {
				...( options.realCards ? {} : { SchemaCard: SchemaCardStub } ),
				SchemaCreatorDialog: SchemaCreatorDialogStub,
				SchemaEditorDialog: SchemaEditorDialogStub,
				SubjectCreatorDialog: SubjectCreatorDialogStub,
				DeletePageDialog: true,
				CdxIcon: true,
			},
		},
	} );
}

function cards( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof SchemaCard>>[] {
	return wrapper.findAllComponents( SchemaCard );
}

function cardNames( wrapper: VueWrapper ): string[] {
	return cards( wrapper ).map( ( card ) => card.props( 'summary' ).name );
}

async function askToDelete( wrapper: VueWrapper, cardIndex: number ): Promise<void> {
	cards( wrapper )[ cardIndex ].vm.$emit( 'delete' );
	await flushPromises();
}

async function confirmDeleted( wrapper: VueWrapper, pageTitle: string ): Promise<void> {
	wrapper.findComponent( DeletePageDialog ).vm.$emit( 'deleted', pageTitle );
	await flushPromises();
}

async function find( wrapper: VueWrapper, text: string ): Promise<void> {
	await wrapper.find( 'input[type="search"]' ).setValue( text );
}

describe( 'SchemasPage', () => {

	beforeEach( () => {
		mayCreateSchemas = false;
		mayEditSchemas = false;
		mayDeleteSchemas = false;
		canCreateSchemasRef.value = false;
		canEditSchemaRef.value = false;
		canDeleteSchemaRef.value = false;
		mayCreateSubjectPages = false;
		canCreateSubjectPageRef.value = false;
		getSchemaMock.mockReset();
		saveSchemaMock.mockReset().mockResolvedValue( undefined );
		pinia = createPinia();
		setActivePinia( pinia );
		schemaStore = useSchemaStore();
		listSchemas( [ 'Artist', 'Artwork', 'City' ] );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'shows a card for every Schema', async () => {
		const names = [ 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M' ];
		listSchemas( names );
		const wrapper = mountPage();
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( names );
	} );

	it( 'tells the cards whether the wiki can list Subjects', async () => {
		const wrapper = mountPage( { subjectListAvailable: false } );
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'subjectListAvailable' ) ).toBe( false );
	} );

	it( 'keeps the Schemas whose name contains the find text in any case', async () => {
		listSchemas( [ 'Artist', 'Artwork', 'City', 'Department' ] );
		const wrapper = mountPage();
		await flushPromises();

		await find( wrapper, 'ART' );

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Artwork', 'Department' ] );
	} );

	it( 'ignores spaces around the find text', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await find( wrapper, ' City ' );

		expect( cardNames( wrapper ) ).toEqual( [ 'City' ] );
	} );

	it( 'finds among the Schemas it has without asking the wiki again', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await find( wrapper, 'art' );

		expect( schemaStore.fetchAllSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'says when no Schema matches the find text', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await find( wrapper, ' zzq ' );

		expect( wrapper.text() ).toContain( 'neowiki-schemas-no-matchzzq' );
	} );

	it( 'says when the wiki has no Schemas', async () => {
		listSchemas( [] );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-schemas-empty' );
	} );

	it( 'shows that the Schemas are loading, rather than that there are none', async () => {
		schemaStore.fetchAllSchemaSummaries = vi.fn().mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.find( '.ext-neowiki-schemas-page__loading' ).exists() ).toBe( true );
		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-empty' );
	} );

	it( 'says the Schemas could not be loaded where their cards would be', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		schemaStore.fetchAllSchemaSummaries = vi.fn().mockRejectedValue( new Error( 'Error fetching schema summaries' ) );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-schemas-load-error' );
		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-empty' );
	} );

	it( 'shows the newest Subjects of a card found again without asking for them again', async () => {
		const scroll = stubIntersectionObserver();
		const getSubjectSummaries = vi.fn().mockResolvedValue( {
			subjects: [ {
				id: 's1demo1aaaaaaa3', displayName: 'Johannes Vermeer', displayNameIsGenerated: false, schema: 'Artist',
				pageId: 1, pageTitle: 'Johannes Vermeer', lastEdited: '2026-10-01T14:02:00Z',
			} ],
			nextCursor: null,
		} );
		const wrapper = mountPage( { realCards: true, subjectSummaryLookup: { getSubjectSummaries } } );
		await flushPromises();
		scroll.setInView( cards( wrapper )[ 0 ].element, true );
		await flushPromises();

		await find( wrapper, 'City' );
		await find( wrapper, '' );
		await flushPromises();
		scroll.setInView( cards( wrapper )[ 0 ].element, true );
		await flushPromises();

		expect( cards( wrapper )[ 0 ].text() ).toContain( 'Johannes Vermeer' );
		expect( getSubjectSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'offers Schema creation to a user who may create Schemas', async () => {
		mayCreateSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		await wrapper.find( '.ext-neowiki-schemas-page__create' ).trigger( 'click' );

		expect( wrapper.findComponent( SchemaCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'offers no Schema creation to a user who may not create Schemas', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.find( '.ext-neowiki-schemas-page__create' ).exists() ).toBe( false );
		expect( wrapper.findComponent( SchemaCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'shows the card of a created Schema whatever the find text was', async () => {
		mayCreateSchemas = true;
		const wrapper = mountPage();
		await flushPromises();
		await find( wrapper, 'art' );
		listSchemas( [ 'Artist', 'Artwork', 'Bridge', 'City' ] );

		wrapper.findComponent( SchemaCreatorDialog ).vm.$emit( 'created' );
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Artwork', 'Bridge', 'City' ] );
		expect( wrapper.find<HTMLInputElement>( 'input[type="search"]' ).element.value ).toBe( '' );
	} );

	it( 'keeps the Schemas listed after a create when the first listing arrives later', async () => {
		mayCreateSchemas = true;
		let answerFirstListing: ( listing: SchemaSummary[] ) => void = () => undefined;
		schemaStore.fetchAllSchemaSummaries = vi.fn()
			.mockReturnValueOnce( new Promise( ( resolve ) => {
				answerFirstListing = resolve;
			} ) )
			.mockResolvedValueOnce( summaries( [ 'Artist', 'Bridge' ] ) );
		const wrapper = mountPage();
		await flushPromises();

		wrapper.findComponent( SchemaCreatorDialog ).vm.$emit( 'created' );
		await flushPromises();
		answerFirstListing( summaries( [ 'Artist' ] ) );
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Bridge' ] );
	} );

	it( 'lets the cards offer editing to a user who may edit Schemas', async () => {
		mayEditSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canEdit' ) ).toBe( true );
	} );

	it( 'lets the cards offer deleting to a user who may delete Schemas', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canDelete' ) ).toBe( true );
	} );

	it( 'opens the editor on the Schema of the card', async () => {
		mayEditSchemas = true;
		const artwork = newSchema( { title: 'Artwork' } );
		getSchemaMock.mockResolvedValue( artwork );
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 1 ].vm.$emit( 'edit' );
		await flushPromises();

		expect( getSchemaMock ).toHaveBeenCalledWith( 'Artwork' );
		const editor = wrapper.findComponent( SchemaEditorDialog );
		expect( editor.props( 'open' ) ).toBe( true );
		expect( editor.props( 'initialSchema' ) ).toStrictEqual( artwork );
	} );

	it( 'reports a Schema that could not be fetched instead of opening the editor', async () => {
		mayEditSchemas = true;
		getSchemaMock.mockRejectedValue( new Error( 'Error fetching schema' ) );
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 0 ].vm.$emit( 'edit' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'Error fetching schema', { type: 'error' } );
		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
	} );

	it( 'shows the saved description on the card of the edited Schema', async () => {
		mayEditSchemas = true;
		getSchemaMock.mockResolvedValue( newSchema( { title: 'Artwork' } ) );
		const wrapper = mountPage();
		await flushPromises();
		cards( wrapper )[ 1 ].vm.$emit( 'edit' );
		await flushPromises();

		await wrapper.findComponent( SchemaEditorDialog ).props( 'onSave' )(
			new Schema( 'Artwork', 'A work of art.', new PropertyDefinitionList( [] ) ),
			'comment',
		);
		await flushPromises();

		expect( cards( wrapper )[ 1 ].props( 'summary' ).description ).toBe( 'A work of art.' );
	} );

	it( 'asks to confirm deleting the Schema of the card', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		await askToDelete( wrapper, 1 );

		const dialog = wrapper.findComponent( DeletePageDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'pageTitle' ) ).toBe( 'Schema:Artwork' );
	} );

	it( 'removes the card and the stored Schema once deleted', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();
		const store = useSchemaStore();
		store.setSchema( 'Artwork', newSchema( { title: 'Artwork' } ) );
		await askToDelete( wrapper, 1 );

		await confirmDeleted( wrapper, 'Schema:Artwork' );

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'City' ] );
		expect( () => store.getSchema( 'Artwork' ) ).toThrow();
	} );

	it( 'removes the card of the Schema deleted, not of the one asked about meanwhile', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();
		await askToDelete( wrapper, 1 );
		await askToDelete( wrapper, 2 );

		await confirmDeleted( wrapper, 'Schema:Artwork' );

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'City' ] );
	} );

	it( 'keeps the Schema editor from a user who may not edit Schemas', async () => {
		getSchemaMock.mockResolvedValue( newSchema( { title: 'Artwork' } ) );
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 1 ].vm.$emit( 'edit' );
		await flushPromises();

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
	} );

	it( 'lets the cards offer Subject creation to a user who may create Subject pages', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canCreateSubject' ) ).toBe( true );
	} );

	it( 'opens the Subject creator on the Schema of the card', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 2 ].vm.$emit( 'create-subject' );
		await flushPromises();

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'City' );
	} );

	it( 'closes the Subject creator when it asks to close', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();
		cards( wrapper )[ 0 ].vm.$emit( 'create-subject' );
		await flushPromises();

		wrapper.findComponent( SubjectCreatorDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'keeps the Subject creator from a user who may not create Subject pages', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canCreateSubject' ) ).toBe( false );
		expect( wrapper.findComponent( SubjectCreatorDialog ).exists() ).toBe( false );
	} );

} );
