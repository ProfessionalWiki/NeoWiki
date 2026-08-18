import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';

const EXISTING_SCHEMA_NAME = 'Person';
const NEW_SCHEMA_NAME = 'Company';
const DEBOUNCE_DELAY = 300;

// What the stubbed editor reports about its initial-value field holding text it
// cannot turn into a value. Reset per test by the beforeEach below.
let editorUnparseableInput: UnparseableInput | null = null;

const SchemaEditorStub = defineComponent( {
	name: 'SchemaEditor',
	template: '<div class="schema-editor-stub"></div>',
	props: {
		initialSchema: { type: Object, required: true },
	},
	emits: [ 'change', 'overflow' ],
	methods: {
		getSchema(): Schema {
			return new Schema( '', '', new PropertyDefinitionList( [] ) );
		},
		unparseableInput(): UnparseableInput | null {
			return editorUnparseableInput;
		},
	},
} );

describe( 'SchemaCreator', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	function mountComponent( { attachTo, initialSchema }: { attachTo?: Element; initialSchema?: Schema } = {} ): VueWrapper {
		return mount( SchemaCreator, {
			attachTo,
			props: initialSchema ? { initialSchema } : {},
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
		editorUnparseableInput = null;
		vi.useFakeTimers();

		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.schemaNameExists = vi.fn().mockResolvedValue( false );
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
		schemaStore.schemaNameExists = vi.fn().mockResolvedValue( true );

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

		expect( schemaStore.schemaNameExists ).not.toHaveBeenCalled();
	} );

	it( 'cancels pending duplicate check when user types again', async () => {
		schemaStore.schemaNameExists = vi.fn().mockImplementation(
			( name: string ) => Promise.resolve( name === EXISTING_SCHEMA_NAME ),
		);

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY - 1 );
		await nameInput.setValue( NEW_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
		expect( schemaStore.schemaNameExists ).toHaveBeenCalledWith( NEW_SCHEMA_NAME );
		expect( schemaStore.schemaNameExists ).not.toHaveBeenCalledWith( EXISTING_SCHEMA_NAME );
	} );

	it( 'discards stale duplicate check result when user types during request', async () => {
		let resolveExists: ( value: boolean ) => void;
		schemaStore.schemaNameExists = vi.fn().mockImplementation(
			() => new Promise<boolean>( ( resolve ) => {
				resolveExists = resolve;
			} ),
		);

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );

		await nameInput.setValue( NEW_SCHEMA_NAME );

		resolveExists!( true );
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
			schemaStore.schemaNameExists = vi.fn().mockResolvedValue( true );

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

		it( 'returns the schema with the name and description that were entered', async () => {
			const wrapper = mountComponent();

			const [ nameInput, descriptionInput ] = wrapper.findAll( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await descriptionInput.setValue( 'What this schema is for' );
			await flushPromises();

			const schema = ( wrapper.vm as any ).getSchema() as Schema;

			expect( schema.getName() ).toBe( NEW_SCHEMA_NAME );
			expect( schema.getDescription() ).toBe( 'What this schema is for' );
		} );

		it( 'returns an empty description when none was entered', async () => {
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() ).toBe( '' );
		} );
	} );

	describe( 'unparseableInput', () => {
		it( 'reports nothing while the schema editor reports nothing', () => {
			const wrapper = mountComponent();

			expect( ( wrapper.vm as any ).unparseableInput() ).toBeNull();
		} );

		it( 'forwards what the schema editor reports', () => {
			editorUnparseableInput = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			const wrapper = mountComponent();

			expect( ( wrapper.vm as any ).unparseableInput() ).toEqual( {
				propertyName: 'Score',
				message: 'neowiki-field-invalid-number',
			} );
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

		it( 'reports a change when the description is typed, so the dialog can offer to save', async () => {
			const wrapper = mountComponent();

			const descriptionInput = wrapper.findAll( '.cdx-text-input-stub' )[ 1 ];
			await descriptionInput.setValue( 'Something worth saving' );

			expect( wrapper.emitted( 'change' ) ).toBeTruthy();
		} );

		it( 'clears the description', async () => {
			const wrapper = mountComponent();

			const [ nameInput, descriptionInput ] = wrapper.findAll( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await descriptionInput.setValue( 'Left over from the last one' );

			( wrapper.vm as any ).reset();
			await flushPromises();
			await nameInput.setValue( NEW_SCHEMA_NAME );

			expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() ).toBe( '' );
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

	describe( 'initialSchema prop', () => {
		it( 'pre-populates name from initialSchema', () => {
			const wrapper = mountComponent( {
				initialSchema: new Schema( 'PreFilledName', 'A desc', new PropertyDefinitionList( [] ) ),
			} );

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			expect( ( nameInput.element as HTMLInputElement ).value ).toBe( 'PreFilledName' );
		} );

		it( 'passes initialSchema to SchemaEditor', () => {
			const wrapper = mountComponent( {
				initialSchema: new Schema( 'PreFilledName', 'A desc', new PropertyDefinitionList( [] ) ),
			} );

			const schemaEditor = wrapper.findComponent( { name: 'SchemaEditor' } );
			expect( schemaEditor.props( 'initialSchema' ).getName() ).toBe( 'PreFilledName' );
		} );

		it( 'uses empty schema when no initialSchema provided', () => {
			const wrapper = mountComponent();

			const schemaEditor = wrapper.findComponent( { name: 'SchemaEditor' } );
			expect( schemaEditor.props( 'initialSchema' ).getName() ).toBe( '' );
		} );

		it( 'reset clears to empty state even with initialSchema', async () => {
			const wrapper = mountComponent( {
				initialSchema: new Schema( 'PreFilledName', 'A desc', new PropertyDefinitionList( [] ) ),
			} );

			( wrapper.vm as any ).reset();
			await flushPromises();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			expect( ( nameInput.element as HTMLInputElement ).value ).toBe( '' );
		} );
	} );
} );
