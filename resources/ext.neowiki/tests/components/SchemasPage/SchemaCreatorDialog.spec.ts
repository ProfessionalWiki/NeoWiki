import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

const NEW_SCHEMA_NAME = 'Company';

const SchemaCreatorStub = {
	template: '<div class="schema-creator-stub"></div>',
	emits: [ 'change', 'overflow' ],
	setup() {
		let valid = true;
		const schema: Schema | null = new Schema( NEW_SCHEMA_NAME, 'A description', new PropertyDefinitionList( [] ) );

		const validate = vi.fn( async (): Promise<boolean> => valid );
		const getSchema = vi.fn( (): Schema | null => schema );
		const reset = vi.fn();
		const focus = vi.fn();

		return {
			validate,
			getSchema,
			reset,
			focus,
			setStubValid( v: boolean ) {
				valid = v;
			},
		};
	},
};

const EditSummaryStub = {
	template: '<div class="edit-summary-stub"><button class="save-button" @click="$emit( \'save\', \'\' )">Save</button></div>',
	props: [ 'helpText', 'saveButtonLabel', 'saveDisabled' ],
	emits: [ 'save' ],
};

const CdxDialogStub = {
	template: '<div class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open' ],
};

const CloseConfirmationDialogStub = {
	template: '<div class="close-confirmation-stub"></div>',
	props: [ 'open' ],
	emits: [ 'discard', 'keep-editing' ],
};

describe( 'SchemaCreatorDialog', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	function mountComponent( open = true ): VueWrapper {
		return mount( SchemaCreatorDialog, {
			props: { open },
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaCreator: SchemaCreatorStub,
					EditSummary: EditSummaryStub,
					CloseConfirmationDialog: CloseConfirmationDialogStub,
					CdxDialog: CdxDialogStub,
					CdxIcon: true,
					teleport: true,
				},
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		} );
	}

	beforeEach( () => {
		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );
	} );

	it( 'does not save when validation fails', async () => {
		const wrapper = mountComponent();

		( wrapper.findComponent( SchemaCreatorStub ).vm as any ).setStubValid( false );

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( schemaStore.saveSchema ).not.toHaveBeenCalled();
	} );

	it( 'saves schema and emits created on success', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', 'My summary' );
		await flushPromises();

		expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
			expect.any( Schema ),
			'My summary',
		);

		const createdEvents = wrapper.emitted( 'created' ) as Schema[][];
		expect( createdEvents ).toHaveLength( 1 );
		expect( createdEvents[ 0 ][ 0 ].getName() ).toBe( NEW_SCHEMA_NAME );

		expect( mw.notify ).toHaveBeenCalledWith(
			expect.any( String ),
			expect.objectContaining( { type: 'success' } ),
		);
	} );

	it( 'uses default summary when none provided', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( schemaStore.saveSchema ).toHaveBeenCalledWith(
			expect.any( Schema ),
			expect.any( String ),
		);

		const usedSummary = ( schemaStore.saveSchema as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 1 ];
		expect( usedSummary ).not.toBe( '' );
	} );

	it( 'shows error notification on save failure', async () => {
		schemaStore.saveSchema = vi.fn().mockRejectedValue( new Error( 'Server error' ) );

		const wrapper = mountComponent();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			'Server error',
			expect.objectContaining( { type: 'error' } ),
		);

		expect( wrapper.emitted( 'created' ) ).toBeUndefined();
	} );

	it( 'closes dialog on emit update:open false', async () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );
} );
