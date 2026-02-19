import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

const EXISTING_SCHEMA_NAME = 'Person';
const NEW_SCHEMA_NAME = 'Company';
const DEBOUNCE_DELAY = 300;

const SchemaEditorStub = {
	template: '<div class="schema-editor-stub"></div>',
	props: [ 'initialSchema' ],
	emits: [ 'change', 'overflow' ],
	setup() {
		const getSchema = (): Schema => new Schema( '', 'A description', new PropertyDefinitionList( [] ) );
		return { getSchema };
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
					SchemaEditor: SchemaEditorStub,
					EditSummary: EditSummaryStub,
					CloseConfirmationDialog: CloseConfirmationDialogStub,
					CdxDialog: CdxDialogStub,
					CdxField: {
						name: 'CdxField',
						template: '<div class="cdx-field-stub"><slot /><slot name="label" /></div>',
						props: [ 'status', 'messages' ],
					},
					CdxTextInput: {
						template: '<input class="cdx-text-input-stub" :value="modelValue" @input="$emit( \'update:modelValue\', $event.target.value ); $emit( \'input\' )" />',
						props: [ 'modelValue', 'placeholder' ],
						emits: [ 'update:modelValue', 'input' ],
						methods: { focus: vi.fn() },
					},
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
		vi.useFakeTimers();

		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );
		schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'shows error when name is empty on save', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( schemaStore.saveSchema ).not.toHaveBeenCalled();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'shows error when name already exists', async () => {
		schemaStore.getOrFetchSchema = vi.fn().mockResolvedValue( newSchema( { title: EXISTING_SCHEMA_NAME } ) );

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( schemaStore.saveSchema ).not.toHaveBeenCalled();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'saves schema and emits created on success', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( NEW_SCHEMA_NAME );
		await flushPromises();

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

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( NEW_SCHEMA_NAME );
		await flushPromises();

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

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( NEW_SCHEMA_NAME );
		await flushPromises();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			'Server error',
			expect.objectContaining( { type: 'error' } ),
		);

		expect( wrapper.emitted( 'created' ) ).toBeUndefined();
	} );

	it( 'clears name error when user types', async () => {
		const wrapper = mountComponent();

		await wrapper.findComponent( EditSummary ).vm.$emit( 'save', '' );
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );
		await nameInput.trigger( 'input' );
		await flushPromises();

		expect( field.props( 'status' ) ).toBe( 'default' );
	} );

	it( 'closes dialog on emit update:open false', async () => {
		const wrapper = mountComponent();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
	} );

	it( 'does not show error on initially empty field', () => {
		const wrapper = mountComponent();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
	} );

	it( 'shows required error in real time when name is cleared', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );
		await nameInput.setValue( '' );

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'shows name-taken error after debounce', async () => {
		schemaStore.getOrFetchSchema = vi.fn().mockResolvedValue( newSchema( { title: EXISTING_SCHEMA_NAME } ) );

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );
		await flushPromises();

		expect( field.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'does not check for duplicates before debounce delay', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY - 1 );
		await flushPromises();

		expect( schemaStore.getOrFetchSchema ).not.toHaveBeenCalled();
	} );

	it( 'cancels pending duplicate check when user types again', async () => {
		schemaStore.getOrFetchSchema = vi.fn().mockImplementation( ( name: string ) => {
			if ( name === EXISTING_SCHEMA_NAME ) {
				return Promise.resolve( newSchema( { title: EXISTING_SCHEMA_NAME } ) );
			}
			return Promise.reject( new Error( 'Not found' ) );
		} );

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY - 1 );
		await nameInput.setValue( NEW_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
		expect( schemaStore.getOrFetchSchema ).toHaveBeenCalledWith( NEW_SCHEMA_NAME );
		expect( schemaStore.getOrFetchSchema ).not.toHaveBeenCalledWith( EXISTING_SCHEMA_NAME );
	} );

	it( 'discards stale duplicate check result when user types during request', async () => {
		let resolveSchemaPromise: ( value: Schema ) => void;
		schemaStore.getOrFetchSchema = vi.fn().mockImplementation(
			() => new Promise<Schema>( ( resolve ) => {
				resolveSchemaPromise = resolve;
			} ),
		);

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );

		await nameInput.setValue( NEW_SCHEMA_NAME );

		resolveSchemaPromise!( newSchema( { title: EXISTING_SCHEMA_NAME } ) );
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
	} );
} );
