import { DOMWrapper, mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemaEditorDialog, { type SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import DialogHeader from '@/components/common/DialogHeader.vue';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import TargetSchemasInput from '@/components/SchemaEditor/Property/TargetSchemasInput.vue';
import SchemaCreatorDialog from '@/components/SchemaCreator/SchemaCreatorDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, pressHeaderClose, setupMwMock } from '../../VueTestHelpers.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import type { SubjectSummaries, SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';

const $i18n = createI18nMock();

// The reason the stubbed editor reports for holding the save, and whether it was asked to
// reveal its errors. Reset per test by the beforeEach below.
let editorSaveBlocker: SaveBlocker | null = null;
let editorErrorsRevealed = false;

// Shows the fields the dialog gives it for the Schema's own row, as the real editor does while that row is selected.
const SchemaEditorStub = defineComponent( {
	template: '<div class="schema-editor-stub"><slot name="schema" /></div>',
	props: {
		initialSchema: { type: Object, required: true },
		subjectsMayUseSchema: { type: Boolean, required: true },
	},
	emits: [ 'change' ],
	methods: {
		getSchema(): Schema {
			return newSchema( { title: 'TestSchema', properties: new PropertyDefinitionList( [] ) } );
		},
		saveBlocker(): SaveBlocker | null {
			return editorSaveBlocker;
		},
		revealErrors(): void {
			editorErrorsRevealed = true;
		},
	},
} );

const SummaryActionStub = {
	template: '<div class="edit-summary-stub"></div>',
	props: [ 'helpText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

describe( 'SchemaEditorDialog', () => {
	let onSave: ReturnType<typeof vi.fn<SchemaSaveHandler>>;
	// The Schemas the reader sees Subjects of.
	let schemasWithSubjects: string[];

	const subjectSummaryLookup: SubjectSummaryLookup = {
		getSubjectSummaries: async ( query ) => subjectsOf( query.schema ?? '' ),
		getRecentlyEditedSubjects: async () => [],
	};

	function subjectsOf( schema: string ): SubjectSummaries {
		const subjects = schemasWithSubjects.includes( schema ) ? [ {
			id: 's1ab2cd3ef4gh5i', displayName: 'A Subject', displayNameIsGenerated: false, hostSubject: null,
			schema, pageId: 1, pageTitle: 'A Subject', lastEdited: '2026-11-17T00:00:00Z',
		} ] : [];

		return { subjects, nextCursor: null };
	}

	beforeEach( () => {
		editorSaveBlocker = null;
		editorErrorsRevealed = false;
		onSave = vi.fn<SchemaSaveHandler>();
		schemasWithSubjects = [];
		setupMwMock( { functions: [ 'message', 'msg', 'notify' ] } );
	} );

	const mockSchema = newSchema( { title: 'TestSchema', description: 'A test schema', properties: new PropertyDefinitionList( [] ) } );

	const stubs = {
		SchemaEditor: SchemaEditorStub,
		SummaryAction: SummaryActionStub,
		CloseConfirmationDialog: CloseConfirmationDialogStub,
	};

	function mountOn( initialSchema: Schema ): VueWrapper {
		return mount( SchemaEditorDialog, {
			props: { initialSchema, open: true, onSave },
			global: { mocks: { $i18n }, stubs, provide: { [ Service.SubjectSummaryLookup ]: subjectSummaryLookup } },
		} );
	}

	function mountComponent(): VueWrapper {
		return mountOn( mockSchema );
	}

	describe( 'Save button', () => {
		it( 'disables save when there are no changes', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );

		it( 'enables save after a change is made', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
		} );

		it( 'disables save again when dialog reopens', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );
			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );

			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );

		it( 'disables save while a save is in flight', async () => {
			onSave.mockReturnValue( new Promise( () => {
				// Left in flight, so the dialog is observed while it saves.
			} ) );
			const wrapper = mountComponent();
			await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );

			wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );
	} );

	describe( 'Description', () => {
		function descriptionField( wrapper: VueWrapper ): Omit<DOMWrapper<HTMLTextAreaElement>, 'exists'> {
			return wrapper.findComponent( SchemaEditor ).get<HTMLTextAreaElement>( 'textarea' );
		}

		it( 'shows the description of the schema being edited among the fields of the Schema\'s row', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			expect( descriptionField( wrapper ).element.value ).toBe( 'A test schema' );
		} );

		it( 'enables save once the description is edited', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await descriptionField( wrapper ).setValue( 'Rewritten' );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
		} );

		it( 'saves the edited description', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await descriptionField( wrapper ).setValue( 'Rewritten' );
			wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'a summary' );
			await flushPromises();

			expect( onSave.mock.calls[ 0 ][ 0 ].getDescription() ).toBe( 'Rewritten' );
		} );

		it( 'drops an abandoned description when the dialog reopens', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await descriptionField( wrapper ).setValue( 'Abandoned' );
			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			expect( descriptionField( wrapper ).element.value ).toBe( 'A test schema' );
		} );
	} );

	describe( 'Whether the Subjects are dependent', () => {
		it( 'says so of a Schema whose Subjects are dependent, among the fields of the Schema\'s row', () => {
			const wrapper = mountOn( newSchema( { subjectsAreDependent: true } ) );

			expect( wrapper.findComponent( SchemaEditor ).text() ).toContain( 'neowiki-schema-subjects-are-dependent' );
		} );

		it( 'says nothing of it for a Schema whose Subjects are standalone', () => {
			const wrapper = mountOn( mockSchema );

			expect( wrapper.findComponent( SchemaEditor ).text() ).not.toContain( 'neowiki-schema-subjects-are-dependent' );
		} );
	} );

	describe( 'Subjects that may use the Schema', () => {
		afterEach( () => {
			vi.restoreAllMocks();
		} );

		function editorIsToldSubjectsMayUseSchema( wrapper: VueWrapper ): boolean | undefined {
			return wrapper.findComponent( SchemaEditor ).props( 'subjectsMayUseSchema' );
		}

		async function openedEditor(): Promise<VueWrapper> {
			const wrapper = mountComponent();
			await flushPromises();

			return wrapper;
		}

		async function reopen( wrapper: VueWrapper ): Promise<void> {
			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );
			await flushPromises();
		}

		function nextAnswerNeverLands(): void {
			vi.spyOn( subjectSummaryLookup, 'getSubjectSummaries' ).mockReturnValueOnce( new Promise( () => {
				// Never lands.
			} ) );
		}

		it( 'tells the editor that Subjects may use a Schema the reader sees Subjects of', async () => {
			schemasWithSubjects = [ 'TestSchema' ];

			expect( editorIsToldSubjectsMayUseSchema( await openedEditor() ) ).toBe( true );
		} );

		it( 'tells the editor that no Subject uses a Schema the reader sees no Subjects of', async () => {
			schemasWithSubjects = [ 'Other' ];

			expect( editorIsToldSubjectsMayUseSchema( await openedEditor() ) ).toBe( false );
		} );

		it( 'tells the editor that Subjects may use the Schema while the answer is on its way', async () => {
			nextAnswerNeverLands();

			expect( editorIsToldSubjectsMayUseSchema( await openedEditor() ) ).toBe( true );
		} );

		it( 'tells the editor that Subjects may use the Schema when the wiki does not answer', async () => {
			vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
			vi.spyOn( subjectSummaryLookup, 'getSubjectSummaries' ).mockRejectedValueOnce( new Error( 'offline' ) );

			expect( editorIsToldSubjectsMayUseSchema( await openedEditor() ) ).toBe( true );
		} );

		it( 'asks again each time it opens', async () => {
			schemasWithSubjects = [ 'TestSchema' ];
			const wrapper = await openedEditor();
			schemasWithSubjects = [];

			await reopen( wrapper );

			expect( editorIsToldSubjectsMayUseSchema( wrapper ) ).toBe( false );
		} );

		it( 'tells the editor that Subjects may use the Schema while the answer to a reopening is on its way', async () => {
			const wrapper = await openedEditor();
			nextAnswerNeverLands();

			await reopen( wrapper );

			expect( editorIsToldSubjectsMayUseSchema( wrapper ) ).toBe( true );
		} );

		it( 'goes by the latest answer when an earlier one lands late', async () => {
			let landEarlierAnswer: ( summaries: SubjectSummaries ) => void = () => undefined;
			vi.spyOn( subjectSummaryLookup, 'getSubjectSummaries' ).mockReturnValueOnce( new Promise( ( resolve ) => {
				landEarlierAnswer = resolve;
			} ) );
			schemasWithSubjects = [ 'TestSchema' ];
			const wrapper = await openedEditor();
			await reopen( wrapper );

			landEarlierAnswer( { subjects: [], nextCursor: null } );
			await flushPromises();

			expect( editorIsToldSubjectsMayUseSchema( wrapper ) ).toBe( true );
		} );

		it( 'goes by the Schema a host switches it to', async () => {
			schemasWithSubjects = [ 'TestSchema' ];
			const wrapper = await openedEditor();

			await wrapper.setProps( { initialSchema: newSchema( { title: 'Person' } ) } );
			await flushPromises();

			expect( editorIsToldSubjectsMayUseSchema( wrapper ) ).toBe( false );
		} );
	} );

	it( 'asks before its header\'s Close throws away a change', async () => {
		const wrapper = mountComponent();
		await flushPromises();
		await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );

		await pressHeaderClose( wrapper );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
		expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
	} );

	describe( 'Close button', () => {
		it( 'keeps its close button on wide screens', () => {
			const wrapper = mountComponent();

			expect( wrapper.findComponent( DialogHeader ).props( 'useCloseButton' ) ).toBe( true );
		} );
	} );

	describe( 'Close confirmation', () => {
		it( 'shows confirmation dialog when closing with unsaved changes', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );
			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'closes without confirmation when there are no unsaved changes', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'closes dialog when discard is clicked in confirmation', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );
			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'discard' );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );

		it( 'keeps dialog open when keep-editing is clicked in confirmation', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( SchemaEditor ).vm.$emit( 'change' );
			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			wrapper.findComponent( CloseConfirmationDialog ).vm.$emit( 'keep-editing' );
			await flushPromises();

			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( false );
		} );
	} );

	describe( 'Blocked save', () => {
		async function save( wrapper: VueWrapper ): Promise<void> {
			await wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
			await flushPromises();
		}

		it( 'does not save while the editor reports a reason not to', async () => {
			const wrapper = mountComponent();
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			await save( wrapper );

			expect( onSave ).not.toHaveBeenCalled();
			expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-field-invalid-number',
				{ title: 'Score', type: 'error' },
			);
		} );

		it( 'saves once the editor reports none', async () => {
			const wrapper = mountComponent();
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };
			await save( wrapper );

			editorSaveBlocker = null;
			await save( wrapper );

			expect( onSave ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'reveals the errors of the editor', async () => {
			const wrapper = mountComponent();
			editorSaveBlocker = { propertyName: 'Maker', message: 'neowiki-property-editor-target-schema-required' };

			await save( wrapper );

			expect( editorErrorsRevealed ).toBe( true );
		} );
	} );

	describe( 'Creating the target schema of a relation property', () => {
		// The editor reduced to one relation property, which takes the target it is handed back the
		// way the property editor does.
		const RelationEditorStub = {
			components: { RelationAttributesEditor },
			props: [ 'initialSchema' ],
			emits: [ 'change' ],
			setup() {
				const property = ref<RelationProperty>( newRelationProperty( { targetSchemas: [ 'Person' ] } ) );
				const update = ( attributes: Partial<RelationProperty> ): void => {
					property.value = { ...property.value, ...attributes };
				};
				return { property, update };
			},
			template: '<RelationAttributesEditor :property="property" @update:property="update" />',
		};

		// A Codex dialog like the real one, which takes focus when it opens and hands it back when it closes.
		const SchemaCreatorDialogStub = {
			name: 'SchemaCreatorDialog',
			components: { CdxDialog },
			props: { open: Boolean, initialName: String, subjectsAreDependent: { type: Boolean, default: undefined } },
			emits: [ 'update:open', 'created' ],
			template: '<CdxDialog :open="open" title="schema-creator" />',
		};

		let wrapper: VueWrapper;

		beforeEach( () => {
			const pinia = createPinia();
			setActivePinia( pinia );
			const person = { name: 'Person', description: '', propertyCount: 1 };
			useSchemaStore().fetchAllSchemaSummaries = vi.fn()
				.mockResolvedValueOnce( [ person ] )
				.mockResolvedValue( [ person, { name: 'Artist', description: '', propertyCount: 0 } ] );

			wrapper = mount( SchemaEditorDialog, {
				props: { initialSchema: mockSchema, open: true, onSave },
				attachTo: document.body,
				global: {
					plugins: [ pinia ],
					mocks: { $i18n },
					stubs: { ...stubs, SchemaEditor: RelationEditorStub, SchemaCreatorDialog: SchemaCreatorDialogStub, SeverityInput: true },
					provide: {
						[ Service.SchemaPermissionHints ]: { canCreateSchemas: async () => true },
						[ Service.SubjectSummaryLookup ]: subjectSummaryLookup,
					},
				},
			} );
		} );

		afterEach( () => {
			wrapper.unmount();
		} );

		function targetSchemaField(): HTMLInputElement {
			return wrapper.findComponent( TargetSchemasInput ).find<HTMLInputElement>( 'input' ).element;
		}

		function targetSchemaChips(): string[] {
			return wrapper.findComponent( TargetSchemasInput ).findAll( '.cdx-input-chip__text' ).map( ( chip ) => chip.text() );
		}

		function schemaCreator(): VueWrapper<InstanceType<typeof SchemaCreatorDialog>> {
			return wrapper.findComponent( SchemaCreatorDialog );
		}

		// Chosen from the field, which is where the stacked dialog takes focus from.
		async function chooseCreateOffer( typedName: string ): Promise<void> {
			await flushPromises();
			targetSchemaField().focus();
			wrapper.findComponent( TargetSchemasInput ).vm.$emit( 'create', typedName );
			await flushPromises();
		}

		// From Codex 2.3 a dialog mounted inside another is teleported into it, where Escape closes both.
		it( 'stacks the schema creator next to its own dialog rather than inside it', async () => {
			await chooseCreateOffer( 'Artist' );

			expect( schemaCreator().props( 'initialName' ) ).toBe( 'Artist' );
			expect( wrapper.findComponent( CdxDialog ).findComponent( SchemaCreatorDialog ).exists() ).toBe( false );
		} );

		it( 'decides the Subjects of the Schema it stacks like those of the property\'s targets', async () => {
			await flushPromises();
			wrapper.findComponent( TargetSchemasInput ).vm.$emit( 'create', 'Birth', true );
			await flushPromises();

			expect( schemaCreator().props( 'subjectsAreDependent' ) ).toBe( true );
		} );

		it( 'stacks the schema creator when nothing was typed', async () => {
			await chooseCreateOffer( '' );

			expect( schemaCreator().props( 'initialName' ) ).toBe( '' );
		} );

		it( 'closes the schema creator when it is closed without creating one', async () => {
			await chooseCreateOffer( 'Artist' );

			schemaCreator().vm.$emit( 'update:open', false );
			await flushPromises();

			expect( schemaCreator().exists() ).toBe( false );
		} );

		it( 'returns focus to the target field once the schema is created', async () => {
			await chooseCreateOffer( 'Artist' );

			schemaCreator().vm.$emit( 'created', newSchema( { title: 'Artist' } ) );
			schemaCreator().vm.$emit( 'update:open', false );
			await flushPromises();

			expect( targetSchemaChips() ).toEqual( [ 'Person', 'Artist' ] );
			expect( document.activeElement ).toBe( targetSchemaField() );
		} );
	} );
} );
