import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, toRaw } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SubjectEditPane from '@/components/SubjectEditor/SubjectEditPane.vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import EditableText from '@/components/common/EditableText.vue';
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

// Stores no label and is not a page's Main Subject, so the name it is shown under is its
// Schema's (ADR 31) - the shape a Subject invented mid-edit has.
const schemaNamedSubject = new SubjectWithContext(
	new SubjectId( 's33333333333333' ),
	null,
	'TestSchema',
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
	isNew?: boolean;
	unsavedTargetIds?: readonly string[];
}

let pinia: ReturnType<typeof createPinia>;

function mountPane( {
	subject = defaultSubject,
	schema = defaultSchema,
	nested = false,
	isNew = false,
	unsavedTargetIds = [],
}: MountPaneOptions = {} ): VueWrapper {
	return mount( SubjectEditPane, {
		props: {
			subject,
			schema,
			nested,
			isNew,
			unsavedTargetIds,
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

	function schemaWithTextProperty( propertyName: string ): Schema {
		return new Schema(
			'TestSchema',
			'A test schema',
			new PropertyDefinitionList( [ newTextProperty( { name: propertyName } ) ] ),
		);
	}

	function sentPropertyNames( validate: ReturnType<typeof vi.fn>, call: number ): string[] {
		const statements = validate.mock.calls[ call ][ 2 ] as StatementList;
		return [ ...statements ].map( ( s ) => s.propertyName.toString() );
	}

	it( 'revalidates when the schema prop is replaced', async () => {
		const violation: SubjectViolation = {
			propertyName: 'Name', code: 'max-length', args: [ 5 ], severity: 'error', valuePartIndex: null,
		};
		const validate = vi.fn().mockResolvedValueOnce( [ violation ] ).mockResolvedValue( [] );
		useSubjectStore().validateSubjectUpdate = validate;
		const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithTextProperty( 'Name' ) } );
		await flushPromises();
		expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [ violation ] );

		await wrapper.setProps( { schema: schemaWithTextProperty( 'Name' ) } );
		await flushPromises();

		expect( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) ).toEqual( [] );
	} );

	// The schema decides which fields the editor renders, so reading the form any earlier
	// validates the fields the replaced schema was showing.
	it( 'reads the form under the new schema, not the one it replaced', async () => {
		const validate = vi.fn().mockResolvedValue( [] );
		useSubjectStore().validateSubjectUpdate = validate;
		const wrapper = mountPane( { subject: subjectWithOnlyName, schema: schemaWithTextProperty( 'Name' ) } );
		await flushPromises();
		expect( sentPropertyNames( validate, 0 ) ).toEqual( [ 'Name' ] );

		await wrapper.setProps( { schema: schemaWithTextProperty( 'Nickname' ) } );
		await flushPromises();

		// The renamed field starts empty, so a read of the new form sends no values; the
		// old form would still have sent Name.
		expect( validate ).toHaveBeenCalledTimes( 2 );
		expect( sentPropertyNames( validate, 1 ) ).toEqual( [] );
	} );

	function violation( code: string, propertyName: string ): SubjectViolation {
		return { propertyName, code, args: [], severity: 'error', valuePartIndex: null };
	}

	function violationCodes( wrapper: VueWrapper ): string[] {
		return ( wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[] )
			.map( ( v ) => v.code );
	}

	// A Subject this session invented has no stored version to dry-run an update against, so the
	// pane validates it the way the subject creator does.
	describe( 'Validating a subject the server has never seen', () => {
		function stubCreationValidation( violations: SubjectViolation[] = [] ): ReturnType<typeof vi.fn> {
			const validate = vi.fn().mockResolvedValue( violations );
			useSubjectStore().validateSubject = validate;
			return validate;
		}

		it( 'validates it under its own label, schema and current values', async () => {
			const validate = stubCreationValidation();

			mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge, isNew: true } );
			await flushPromises();

			expect( validate ).toHaveBeenCalledWith( 'Test Subject', 'TestSchema', expect.any( StatementList ) );
			expect( sentPropertyNames( validate, 0 ) ).toEqual( [ 'Name' ] );
		} );

		it( 'never dry-runs it as an update, which has no stored version to run against', async () => {
			stubCreationValidation();
			const update = vi.fn().mockResolvedValue( [] );
			useSubjectStore().validateSubjectUpdate = update;

			mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge, isNew: true } );
			await flushPromises();

			expect( update ).not.toHaveBeenCalled();
		} );

		// Its fields all start empty, so an unfilled required one is a field the user is still on
		// their way to, not a gap. Anything else needs a value to occur.
		it( 'withholds an unfilled required field while surfacing every other violation', async () => {
			stubCreationValidation( [ violation( 'required', 'Name' ), violation( 'max-length', 'Name' ) ] );

			const wrapper = mountPane( { schema: schemaWithNameAndAge, isNew: true } );
			await flushPromises();

			expect( violationCodes( wrapper ) ).toEqual( [ 'max-length' ] );
		} );
	} );

	it( 'validates a subject the server already has as an update', async () => {
		const update = vi.fn().mockResolvedValue( [] );
		useSubjectStore().validateSubjectUpdate = update;

		mountPane( { subject: subjectWithOnlyName, schema: schemaWithNameAndAge } );
		await flushPromises();

		expect( update ).toHaveBeenCalledWith(
			subjectWithOnlyName.getId(),
			'Test Subject',
			expect.any( StatementList ),
		);
	} );

	// An existing subject is expected to be complete, so an empty required field is a real gap.
	it( 'surfaces an unfilled required field on a subject the server already has', async () => {
		useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ violation( 'required', 'Name' ) ] );

		const wrapper = mountPane( { schema: schemaWithNameAndAge } );
		await flushPromises();

		expect( violationCodes( wrapper ) ).toEqual( [ 'required' ] );
	} );

	// A relation naming a Subject this session invented resolves for the user, who has that
	// Subject in front of them, and not for the server, which has never been told about it.
	describe( 'Complaints about a relation target this session has not written yet', () => {
		const unwrittenId = 's22222222222222';
		const otherId = 's99999999999999';

		function targetNotFound( id: string ): SubjectViolation {
			return {
				propertyName: 'Author',
				code: 'relation-target-not-found',
				args: [ id ],
				severity: 'warning',
				valuePartIndex: 0,
			};
		}

		function surfacedViolations( wrapper: VueWrapper ): SubjectViolation[] {
			return wrapper.findComponent( SubjectEditor ).props( 'serverViolations' ) as SubjectViolation[];
		}

		it( 'withholds the dry-run complaint naming an unwritten target, and keeps one naming another', async () => {
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [
				targetNotFound( unwrittenId ),
				targetNotFound( otherId ),
			] );

			const wrapper = mountPane( { schema: relationSchema, unsavedTargetIds: [ unwrittenId ] } );
			await flushPromises();

			expect( surfacedViolations( wrapper ) ).toEqual( [ targetNotFound( otherId ) ] );
		} );

		// A Subject the session invented is validated as a creation, and points at the session's
		// other inventions just as often.
		it( 'withholds it from the creation dry-run as well', async () => {
			useSubjectStore().validateSubject = vi.fn().mockResolvedValue( [
				targetNotFound( unwrittenId ),
				targetNotFound( otherId ),
			] );

			const wrapper = mountPane( {
				schema: relationSchema,
				isNew: true,
				unsavedTargetIds: [ unwrittenId ],
			} );
			await flushPromises();

			expect( surfacedViolations( wrapper ) ).toEqual( [ targetNotFound( otherId ) ] );
		} );

		// A rejected save hands its 422 body straight to the pane, bypassing the dry-run.
		it( 'withholds it from the violations a rejected save pushes in', async () => {
			const wrapper = mountPane( { schema: relationSchema, unsavedTargetIds: [ unwrittenId ] } );
			await flushPromises();

			( wrapper.vm as any ).setServerViolations( [
				targetNotFound( unwrittenId ),
				targetNotFound( otherId ),
			] );
			await nextTick();

			expect( surfacedViolations( wrapper ) ).toEqual( [ targetNotFound( otherId ) ] );
		} );

		// Only "there is no such Subject" is untrue of what the session is about to save. The id
		// carries no exemption of its own, so a violation naming it under any other code stands.
		it( 'leaves a violation of another code naming the same target alone', async () => {
			const otherCode: SubjectViolation = {
				propertyName: 'Author',
				code: 'some-other-code',
				args: [ unwrittenId ],
				severity: 'error',
				valuePartIndex: 0,
			};
			useSubjectStore().validateSubjectUpdate = vi.fn().mockResolvedValue( [ otherCode ] );

			const wrapper = mountPane( { schema: relationSchema, unsavedTargetIds: [ unwrittenId ] } );
			await flushPromises();

			expect( surfacedViolations( wrapper ) ).toEqual( [ otherCode ] );
		} );
	} );

	describe( 'Renaming from a nested pane', () => {
		async function rename( wrapper: VueWrapper, name: string ): Promise<void> {
			await wrapper.get( 'button[aria-label="neowiki-subject-editor-rename"]' ).trigger( 'click' );
			const input = wrapper.get( '.ext-neowiki-editable-text__input input' );
			await input.setValue( name );
			await input.trigger( 'keydown.enter' );
		}

		it( 'saves the committed name as the subject\'s label', async () => {
			const wrapper = mountPane( { nested: true } );

			await rename( wrapper, 'Renamed' );

			expect( ( ( wrapper.vm as any ).buildUpdatedSubject() as Subject ).getLabel() ).toBe( 'Renamed' );
		} );

		// The dialog's own header renames the root Subject; a second field for it would be two
		// places to type one name.
		it( 'leaves the root pane without a rename control', () => {
			const wrapper = mountPane();

			expect( wrapper.findComponent( EditableText ).exists() ).toBe( false );
		} );

		// The empty field stands for "no label", so it previews the name that choice leaves the
		// Subject with rather than describing the field.
		it( 'previews the name a label-less subject is shown under', () => {
			const wrapper = mountPane( { subject: schemaNamedSubject, nested: true } );

			expect( wrapper.findComponent( EditableText ).props( 'placeholder' ) ).toBe( 'TestSchema' );
		} );

		it( 'names the field instead for a subject that already has a label', () => {
			const wrapper = mountPane( { nested: true } );

			expect( wrapper.findComponent( EditableText ).props( 'placeholder' ) )
				.toBe( 'neowiki-subject-editor-label-field' );
		} );
	} );

} );
