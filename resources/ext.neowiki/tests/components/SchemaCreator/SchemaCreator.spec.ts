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
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';

const EXISTING_SCHEMA_NAME = 'Person';
const NEW_SCHEMA_NAME = 'Company';
const DEBOUNCE_DELAY = 300;

// The reason the stubbed editor reports for holding the save, and the Schema it holds. Reset per
// test by the beforeEach below.
let editorSaveBlocker: SaveBlocker | null = null;
let editorSchema: Schema;

const SchemaEditorStub = defineComponent( {
	name: 'SchemaEditor',
	template: '<div class="schema-editor-stub"></div>',
	props: {
		initialSchema: { type: Object, required: true },
	},
	emits: [ 'change' ],
	methods: {
		getSchema(): Schema {
			return editorSchema;
		},
		saveBlocker(): SaveBlocker | null {
			return editorSaveBlocker;
		},
	},
} );

describe( 'SchemaCreator', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	function mountComponent( { initialName }: { initialName?: string } = {} ): VueWrapper {
		return mount( SchemaCreator, {
			props: initialName === undefined ? {} : { initialName },
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
							focus(): void {
								( this.$el as HTMLInputElement ).focus();
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
		editorSaveBlocker = null;
		editorSchema = new Schema( '', '', new PropertyDefinitionList( [] ) );
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

	it( 'does not show error on initially empty field', async () => {
		const wrapper = mountComponent();
		await flushPromises();

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

		it( 'returns the property definitions made in the schema editor', async () => {
			editorSchema = new Schema( '', '', new PropertyDefinitionList( [ newTextProperty( { name: 'Founded' } ) ] ) );
			const wrapper = mountComponent();

			await wrapper.find( '.cdx-text-input-stub' ).setValue( NEW_SCHEMA_NAME );

			const schema = ( wrapper.vm as any ).getSchema() as Schema;
			expect( [ ...schema.getPropertyDefinitions() ].map( ( property ) => property.name.toString() ) ).toEqual( [ 'Founded' ] );
		} );

		it( 'returns an empty description when the field is empty, even where the editor holds one', async () => {
			editorSchema = new Schema( '', 'The description of the Schema duplicated', new PropertyDefinitionList( [] ) );
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() ).toBe( '' );
		} );
	} );

	describe( 'saveBlocker', () => {
		it( 'reports nothing while the schema editor reports nothing', () => {
			const wrapper = mountComponent();

			expect( ( wrapper.vm as any ).saveBlocker() ).toBeNull();
		} );

		it( 'forwards what the schema editor reports', () => {
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			const wrapper = mountComponent();

			expect( ( wrapper.vm as any ).saveBlocker() ).toEqual( {
				propertyName: 'Score',
				message: 'neowiki-field-invalid-number',
			} );
		} );
	} );

	it( 'reports a change when the description is typed, so the dialog can offer to save', async () => {
		const wrapper = mountComponent();

		const descriptionInput = wrapper.findAll( '.cdx-text-input-stub' )[ 1 ];
		await descriptionInput.setValue( 'Something worth saving' );

		expect( wrapper.emitted( 'change' ) ).toBeTruthy();
	} );

	it( 'emits change when name is typed', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );

		expect( wrapper.emitted( 'change' ) ).toBeTruthy();
	} );

	it( 'starts with the name it is given', () => {
		const wrapper = mountComponent( { initialName: 'Artwork' } );

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		expect( ( nameInput.element as HTMLInputElement ).value ).toBe( 'Artwork' );
	} );
} );
