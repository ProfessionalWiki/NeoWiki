import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';
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

describe( 'SchemaCreator', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	function mountComponent( { attachTo }: { attachTo?: Element } = {} ): VueWrapper {
		return mount( SchemaCreator, {
			attachTo,
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaEditor: SchemaEditorStub,
					CdxField: {
						name: 'CdxField',
						template: '<div class="cdx-field-stub"><slot /><slot name="label" /></div>',
						props: [ 'status', 'messages' ],
					},
					CdxTextInput: {
						template: '<input class="cdx-text-input-stub" :value="modelValue" @input="$emit( \'update:modelValue\', $event.target.value ); $emit( \'input\' )" />',
						props: [ 'modelValue', 'placeholder' ],
						emits: [ 'update:modelValue', 'input' ],
						methods: {
							focus() {
								this.$el.focus();
							},
						},
					},
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
		schemaStore.getOrFetchSchema = vi.fn().mockRejectedValue( new Error( 'Not found' ) );
	} );

	afterEach( () => {
		vi.useRealTimers();
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

	it( 'clears name error when user types', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );
		await nameInput.setValue( '' );

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );

		await nameInput.setValue( 'B' );
		await nameInput.trigger( 'input' );
		await flushPromises();

		expect( field.props( 'status' ) ).toBe( 'default' );
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

	describe( 'validate', () => {
		it( 'returns false and shows error when name is empty', async () => {
			const wrapper = mountComponent();

			const valid = await ( wrapper.vm as any ).validate();

			expect( valid ).toBe( false );
			const field = wrapper.findComponent( { name: 'CdxField' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'returns false when name already exists', async () => {
			schemaStore.getOrFetchSchema = vi.fn().mockResolvedValue( newSchema( { title: EXISTING_SCHEMA_NAME } ) );

			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( EXISTING_SCHEMA_NAME );
			await flushPromises();

			const valid = await ( wrapper.vm as any ).validate();

			expect( valid ).toBe( false );
			const field = wrapper.findComponent( { name: 'CdxField' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'returns true when name is available', async () => {
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			const valid = await ( wrapper.vm as any ).validate();

			expect( valid ).toBe( true );
		} );
	} );

	describe( 'getSchema', () => {
		it( 'returns null when name is empty', () => {
			const wrapper = mountComponent();

			const schema = ( wrapper.vm as any ).getSchema();

			expect( schema ).toBeNull();
		} );

		it( 'returns schema with name and description from SchemaEditor', async () => {
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			const schema = ( wrapper.vm as any ).getSchema() as Schema;

			expect( schema.getName() ).toBe( NEW_SCHEMA_NAME );
			expect( schema.getDescription() ).toBe( 'A description' );
		} );
	} );

	describe( 'reset', () => {
		it( 'clears name and errors', async () => {
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( 'Something' );
			await nameInput.setValue( '' );

			const field = wrapper.findComponent( { name: 'CdxField' } );
			expect( field.props( 'status' ) ).toBe( 'error' );

			( wrapper.vm as any ).reset();
			await flushPromises();

			expect( field.props( 'status' ) ).toBe( 'default' );
		} );
	} );

	it( 'focuses name input on focus()', () => {
		const wrapper = mountComponent( { attachTo: document.body } );

		( wrapper.vm as any ).focus();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		expect( nameInput.element ).toBe( document.activeElement );

		wrapper.unmount();
	} );

	it( 'emits change when name is typed', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );

		expect( wrapper.emitted( 'change' ) ).toBeTruthy();
	} );
} );
