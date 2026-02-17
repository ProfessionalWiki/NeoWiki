import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

const SchemaEditorStub = {
	template: '<div class="schema-editor-stub"></div>',
	props: [ 'initialSchema' ],
	emits: [ 'overflow', 'change' ],
	setup() {
		const getSchema = (): Schema => new Schema( 'TestSchema', '', new PropertyDefinitionList( [] ) );
		return { getSchema };
	},
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

describe( 'SchemaEditorDialog', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'notify' ] } );
	} );

	const mockSchema = new Schema( 'TestSchema', 'A test schema', new PropertyDefinitionList( [] ) );

	const stubs = {
		SchemaEditor: SchemaEditorStub,
		EditSummary: true,
		CloseConfirmationDialog: CloseConfirmationDialogStub,
	};

	function mountComponent(): VueWrapper {
		return mount( SchemaEditorDialog, {
			props: {
				initialSchema: mockSchema,
				open: true,
				onSave: vi.fn(),
			},
			global: {
				mocks: { $i18n },
				stubs,
			},
		} );
	}

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
} );
