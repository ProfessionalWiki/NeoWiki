import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import SchemaEditorDialog, { type SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import EditableText from '@/components/common/EditableText.vue';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
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
} );
