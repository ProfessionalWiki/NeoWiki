import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, toRaw } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SubjectEditPane from '@/components/SubjectEditor/SubjectEditPane.vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { newRelation, newStringValue, RelationValue } from '@/domain/Value.ts';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newSchema } from '@/TestHelpers.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import type { SubjectViolation } from '@/domain/SubjectViolation';

const $i18n = createI18nMock();

// Built directly rather than through TestHelpers.newSubject, so it carries the page
// context a Subject read from the server has.
const defaultSubject = new SubjectWithContext(
	new SubjectId( 's11111111111111' ),
	'Test Subject',
	'Test Subject',
	'TestSchema',
	new StatementList( [] ),
	new PageIdentifiers( 42, 'Test page' ),
);

const defaultSchema = newSchema();

const schemaWithNameAndAge = new Schema(
	'TestSchema',
	'A test schema',
	new PropertyDefinitionList( [ newTextProperty( { name: 'Name' } ), newNumberProperty( { name: 'Age' } ) ] ),
);

// Stores no label (ADR 31), so its name on screen is the server's derived one: here the
// page it is the Main Subject of.
const labellessSubject = new SubjectWithContext(
	new SubjectId( 's11111111111111' ),
	null,
	'Test page',
	'TestSchema',
	new StatementList( [] ),
	new PageIdentifiers( 42, 'Test page' ),
);

const subjectWithOnlyName = new SubjectWithContext(
	new SubjectId( 's11111111111111' ),
	'Test Subject',
	'Test Subject',
	'TestSchema',
	new StatementList( [ new Statement( new PropertyName( 'Name' ), TextType.typeName, newStringValue( 'Alice' ) ) ] ),
	new PageIdentifiers( 42, 'Test page' ),
);

interface MountPaneOptions {
	subject?: Subject;
	schema?: Schema;
	nested?: boolean;
}

let pinia: ReturnType<typeof createPinia>;

function mountPane( {
	subject = defaultSubject,
	schema = defaultSchema,
	nested = false,
}: MountPaneOptions = {} ): VueWrapper {
	return mount( SubjectEditPane, {
		props: {
			subject,
			schema,
			nested,
		},
		global: {
			mocks: {
				$i18n,
			},
			plugins: [ pinia ],
			provide: NeoWikiTestServices.getServices(),
			directives: {
				tooltip: {},
			},
			stubs: {
				teleport: true,
			},
		},
	} );
}

// A client-held copy of subjectWithOnlyName with its own label and Name value, so an
// assertion can tell which copy the form is showing.
function namedCopy( label: string, name: string ): Subject {
	return subjectWithOnlyName
		.withLabel( label )
		.withStatements( new StatementList( [
			new Statement( new PropertyName( 'Name' ), TextType.typeName, newStringValue( name ) ),
		] ) );
}

function fieldValue( wrapper: VueWrapper, propertyName: string ): unknown {
	const statements = wrapper.findComponent( SubjectEditor ).props( 'statements' ) as StatementList;
	return [ ...statements ].find( ( s ) => s.propertyName.toString() === propertyName )?.value;
}

// A relation field with a target picked, as the pane shows it for a Subject read from the server.
const relationSchema = new Schema(
	'TestSchema',
	'A test schema',
	new PropertyDefinitionList( [ newRelationProperty( { name: 'Author' } ) ] ),
);

const subjectWithAuthor = new SubjectWithContext(
	new SubjectId( 's11111111111111' ),
	'Test Subject',
	'Test Subject',
	'TestSchema',
	new StatementList( [ new Statement(
		new PropertyName( 'Author' ),
		RelationType.typeName,
		new RelationValue( [ newRelation( undefined, 's22222222222222' ) ] ),
	) ] ),
	new PageIdentifiers( 42, 'Test page' ),
);

describe( 'SubjectEditPane', () => {
	beforeEach( () => {
		setupMwMock( {
			functions: [ 'message', 'msg', 'notify', 'config', 'util' ],
			messages: {
				// The parameter sits mid-string, so an assertion can tell the page name apart from
				// the sentence around it.
				'neowiki-subject-editor-stored-on': ( page ) => `Kept on ${ page } for now`,
			},
			// Debounce 0 is blur-only mode: the dry-run fires on blur / pre-save
			// (via flush()), which runs synchronously in tests.
			config: { wgNeoWikiValidationDebounceMs: 0 },
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		// The dry-run validation runs alongside the live validators; stub it so
		// it does not reach the network and stays out of the way of these tests.
		useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [] );
	} );

	// Only a host that can open a target in place turns the per-target edit button on; the
	// pane is that host, and nothing else on this branch provides the key.
	it( 'offers to edit a relation target in place', () => {
		const wrapper = mountPane( { subject: subjectWithAuthor, schema: relationSchema } );

		expect( wrapper.find( '.ext-neowiki-relation-input__edit-target' ).exists() ).toBe( true );
	} );

	it( 'renders one field per schema property, including properties the subject lacks', () => {
		const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge } );

		// Direct children only: each value-input component nests a .cdx-field of its own,
		// so an unscoped selector double-counts.
		expect( wrapper.findAll( '.ext-neowiki-subject-editor > .cdx-field' ) ).toHaveLength( 2 );
	} );

	it( 'flips hasChanged when a value input reports change', async () => {
		const wrapper = mountPane();

		expect( ( wrapper.vm as any ).hasChanged ).toBe( false );

		wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
		await nextTick();

		expect( ( wrapper.vm as any ).hasChanged ).toBe( true );
	} );

	it( 'buildUpdatedSubject drops valueless statements', () => {
		const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge } );

		const updated = ( wrapper.vm as any ).buildUpdatedSubject() as Subject | null;

		expect( updated ).not.toBeNull();
		expect( [ ...updated!.getStatements() ] ).toHaveLength( 1 );
	} );

	it( 'routes anchorless server violations to the banner and anchored ones to the field', async () => {
		// A schema that declares 'Name', so its violation has a field on screen to anchor to;
		// the subject holds no statements, so anchoring against the subject instead of the
		// schema's rendered fields would misroute it. 'Ghost' is anchorless despite naming
		// a property.
		const wrapper = mountPane( { schema: schemaWithNameAndAge } );

		( wrapper.vm as any ).setServerViolations( [
			{ propertyName: null, code: 'some-subject-level-code', args: [], severity: 'error', valuePartIndex: null },
			{ propertyName: 'Name', code: 'some-field-code', args: [], severity: 'error', valuePartIndex: null },
			{ propertyName: 'Ghost', code: 'some-unrendered-code', args: [], severity: 'error', valuePartIndex: null },
		] as SubjectViolation[] );
		await nextTick();

		const bannered = wrapper.findComponent( SubjectViolationBanners )
			.props( 'violations' ) as SubjectViolation[];
		expect( bannered.map( ( violation ) => violation.code ) )
			.toStrictEqual( [ 'some-subject-level-code', 'some-unrendered-code' ] );
		// The field-anchored one still reaches the editor, which renders it at its field.
		expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toHaveLength( 3 );
	} );

	// The host replaces the Subject when it opens the editor on another root; the pane starts
	// over from it, label and fields alike.
	it( 'starts over from a replaced subject', async () => {
		const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge } );

		expect( ( wrapper.vm as any ).label ).toBe( 'Test Subject' );
		expect( fieldValue( wrapper, 'Name' ) ).toEqual( newStringValue( 'Alice' ) );

		await wrapper.setProps( { subject: namedCopy( 'Replaced', 'Bob' ) } );

		expect( ( wrapper.vm as any ).label ).toBe( 'Replaced' );
		expect( fieldValue( wrapper, 'Name' ) ).toEqual( newStringValue( 'Bob' ) );
	} );

	describe( 'The snapshot the tree reads', () => {
		it( 'exposes the harvested subject after a relation change, and the seeded one before', async () => {
			const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge } );

			// Through toRaw: the harness hands props down through a reactive container, so the
			// pane sees a proxy of the very Subject passed in.
			expect( toRaw( ( wrapper.vm as any ).editedSubject ) ).toBe( subjectWithOnlyName );

			wrapper.findComponent( SubjectEditor ).vm.$emit( 'relation-change' );
			await nextTick();

			expect( toRaw( ( wrapper.vm as any ).editedSubject ) ).not.toBe( subjectWithOnlyName );
			expect( ( wrapper.vm as any ).editedSubject )
				.toEqual( ( wrapper.vm as any ).buildUpdatedSubject() );
		} );

		// A reopened pane would otherwise seed the tree with relations the form no longer shows.
		it( 'follows a replaced subject rather than the earlier harvest', async () => {
			const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge } );
			wrapper.findComponent( SubjectEditor ).vm.$emit( 'relation-change' );
			await nextTick();

			const replacement = namedCopy( 'Replaced', 'Carol' );
			await wrapper.setProps( { subject: replacement } );

			expect( toRaw( ( wrapper.vm as any ).editedSubject ) ).toBe( replacement );
		} );
	} );

	describe( 'Where the subject is stored', () => {
		function storageLine( wrapper: VueWrapper ): Omit<DOMWrapper<Element>, 'exists'> {
			return wrapper.get( '.ext-neowiki-subject-edit-pane__storage' );
		}

		// A wrapping link would make the words around the name clickable, and a message of their
		// own would fix their place in the sentence for every language.
		it( 'links the page name alone, inside the message the translation orders', () => {
			const wrapper = mountPane( { nested: true } );

			expect( storageLine( wrapper ).text() ).toBe( 'Kept on Test page for now' );
			expect( wrapper.get( '.ext-neowiki-subject-edit-pane__page' ).text() ).toBe( 'Test page' );
		} );

		it( 'links the storage page in a new tab, so following it cannot discard a pending edit', () => {
			const wrapper = mountPane( { nested: true } );

			const link = wrapper.get( '.ext-neowiki-subject-edit-pane__page' );
			expect( link.attributes( 'href' ) ).toBe( '/wiki/Test page' );
			expect( link.attributes( 'target' ) ).toBe( '_blank' );
			expect( link.attributes( 'rel' ) ).toBe( 'noopener' );
		} );

		// The root's Subject is stored on the page the editor was opened from.
		it( 'says nothing about storage in the root pane', () => {
			const wrapper = mountPane();

			expect( wrapper.find( '.ext-neowiki-subject-edit-pane__storage' ).exists() ).toBe( false );
		} );

		// The navigator already draws a dot on every edited subject. A second dot here would show
		// on nested subjects alone, so an edited child would carry two and an edited root none.
		it( 'leaves the unsaved dot to the navigator when the form is edited', async () => {
			const wrapper = mountPane( { nested: true } );

			wrapper.findComponent( SubjectEditor ).vm.$emit( 'change' );
			await nextTick();

			expect( ( wrapper.vm as any ).hasChanged ).toBe( true );
			expect( wrapper.find( '.ext-neowiki-unsaved-dot' ).exists() ).toBe( false );
		} );
	} );

	// The pane prints no name of its own, and may hold a Subject other than the one the dialog
	// is titled after, so the region's name is all a screen-reader user has.
	it( 'names the pane region after its own subject', () => {
		const wrapper = mountPane( { nested: true } );

		expect( wrapper.get( '.ext-neowiki-subject-edit-pane' ).attributes( 'aria-label' ) )
			.toBe( 'Test Subject' );
	} );

	// The dialog already carries that name; a second region repeating it is one more boundary
	// to cross on the way to the form.
	it( 'leaves the root pane\'s region unnamed', () => {
		const wrapper = mountPane();

		expect( wrapper.get( '.ext-neowiki-subject-edit-pane' ).attributes( 'aria-label' ) )
			.toBeUndefined();
	} );

	it( 'names the region of a label-less subject by its display name', () => {
		const wrapper = mountPane( { subject: labellessSubject, nested: true } );

		expect( wrapper.get( '.ext-neowiki-subject-edit-pane' ).attributes( 'aria-label' ) )
			.toBe( 'Test page' );
	} );

	// The field edits the stored label, which is empty here.
	it( 'starts the label field empty for a label-less subject', () => {
		const wrapper = mountPane( { subject: labellessSubject } );

		expect( ( wrapper.vm as any ).label ).toBe( '' );
	} );

	// A cleared field means the Subject has no label, not that it has an empty one.
	it( 'saves a cleared label as no label', () => {
		const wrapper = mountPane();

		( wrapper.vm as any ).setLabel( '   ' );

		expect( ( ( wrapper.vm as any ).buildUpdatedSubject() as Subject ).getLabel() ).toBeNull();
	} );

	it( 'validates a cleared label as no label', async () => {
		const validate = vi.fn().mockResolvedValue( [] );
		useSubjectStore().validateSubjectUpdate = validate;
		const wrapper = mountPane();

		( wrapper.vm as any ).setLabel( '' );
		await flushPromises();

		expect( validate ).toHaveBeenLastCalledWith(
			defaultSubject.getId(),
			null,
			expect.any( StatementList ),
		);
	} );

} );
