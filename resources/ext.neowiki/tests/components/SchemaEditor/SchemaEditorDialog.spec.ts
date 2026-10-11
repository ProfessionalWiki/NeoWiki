import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemaEditorDialog, { type SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import EditableText from '@/components/common/EditableText.vue';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
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
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';

const $i18n = createI18nMock();

// The reason the stubbed editor reports for holding the save. Reset per test by
// the beforeEach below.
let editorSaveBlocker: SaveBlocker | null = null;

const SchemaEditorStub = defineComponent( {
	template: '<div class="schema-editor-stub"></div>',
	props: {
		initialSchema: { type: Object, required: true },
		description: { type: String, required: true },
	},
	emits: [ 'change' ],
	methods: {
		// Mirrors the real editor, which applies the host-owned description.
		getSchema(): Schema {
			return new Schema( 'TestSchema', this.description, new PropertyDefinitionList( [] ) );
		},
		saveBlocker(): SaveBlocker | null {
			return editorSaveBlocker;
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

	beforeEach( () => {
		editorSaveBlocker = null;
		onSave = vi.fn<SchemaSaveHandler>();
		setupMwMock( { functions: [ 'message', 'msg', 'notify' ] } );
	} );

	const mockSchema = new Schema( 'TestSchema', 'A test schema', new PropertyDefinitionList( [] ) );

	const stubs = {
		SchemaEditor: SchemaEditorStub,
		SummaryAction: SummaryActionStub,
		CloseConfirmationDialog: CloseConfirmationDialogStub,
	};

	function mountComponent(): VueWrapper {
		return mount( SchemaEditorDialog, {
			props: {
				initialSchema: mockSchema,
				open: true,
				onSave,
			},
			global: {
				mocks: { $i18n },
				stubs,
			},
		} );
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
	} );

	describe( 'Description', () => {
		async function typeDescription( wrapper: VueWrapper, text: string ): Promise<void> {
			const field = wrapper.findComponent( EditableText );
			await field.get( 'button[aria-label="neowiki-schema-editor-description-edit"]' ).trigger( 'click' );
			await field.get( 'textarea' ).setValue( text );
		}

		it( 'shows the description of the schema being edited', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			expect( wrapper.findComponent( EditableText ).props( 'modelValue' ) ).toBe( 'A test schema' );
		} );

		it( 'passes the description down to the editor', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( EditableText ).vm.$emit( 'update:modelValue', 'Rewritten' );

			expect( wrapper.findComponent( SchemaEditor ).props( 'description' ) ).toBe( 'Rewritten' );
		} );

		it( 'enables save once the description is edited', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( EditableText ).vm.$emit( 'update:modelValue', 'Rewritten' );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
		} );

		it( 'enables save while a typed description is still open', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( EditableText ).vm.$emit( 'dirty', true );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
		} );

		it( 'disables save again when the typed description is discarded', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( EditableText ).vm.$emit( 'dirty', true );
			await wrapper.findComponent( EditableText ).vm.$emit( 'dirty', false );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );

		it( 'asks before closing while a typed description is still open', async () => {
			const wrapper = mountComponent();
			await flushPromises();
			await wrapper.findComponent( EditableText ).vm.$emit( 'dirty', true );

			wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
			await flushPromises();

			expect( wrapper.findComponent( CloseConfirmationDialog ).props( 'open' ) ).toBe( true );
		} );

		it( 'drops a dirty draft when the dialog reopens', async () => {
			const wrapper = mountComponent();
			await flushPromises();
			await typeDescription( wrapper, 'Rewr' );

			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );

			expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		} );

		it( 'saves a description still being typed', async () => {
			const wrapper = mountComponent();
			await flushPromises();
			await typeDescription( wrapper, 'Typed' );

			wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'a summary' );
			await flushPromises();

			expect( onSave.mock.calls[ 0 ][ 0 ].getDescription() ).toBe( 'Typed' );
		} );

		it( 'saves the edited description', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( EditableText ).vm.$emit( 'update:modelValue', 'Rewritten' );
			wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'a summary' );
			await flushPromises();

			expect( onSave.mock.calls[ 0 ][ 0 ].getDescription() ).toBe( 'Rewritten' );
		} );

		it( 'drops an abandoned description when the dialog reopens', async () => {
			const wrapper = mountComponent();
			await flushPromises();

			await wrapper.findComponent( EditableText ).vm.$emit( 'update:modelValue', 'Abandoned' );
			await wrapper.setProps( { open: false } );
			await wrapper.setProps( { open: true } );
			await flushPromises();

			expect( wrapper.findComponent( EditableText ).props( 'modelValue' ) ).toBe( 'A test schema' );
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
	} );

	describe( 'Creating the target schema of a relation property', () => {
		// The editor reduced to one relation property, which takes the target it is handed back the
		// way the property editor does.
		const RelationEditorStub = {
			components: { RelationAttributesEditor },
			props: [ 'initialSchema', 'description' ],
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
			props: [ 'open', 'initialName' ],
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
