import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TextInput from '@/components/Value/TextInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';
import { newTextProperty } from '@neo/domain/valueFormats/Text';

describe( 'TextInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	const createWrapper = ( propsData: Partial<InstanceType<typeof TextInput>['$props']> = {} ): VueWrapper<InstanceType<typeof TextInput>> => mount( TextInput, {
		props: {
			modelValue: newStringValue( 'Test' ),
			label: 'Test Label',
			property: newTextProperty( {} ),
			...propsData
		}
	} );

	describe( 'Rendering component', () => {
		it( 'renders single text field correctly', () => {
			const wrapper = createWrapper();
			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
			expect( wrapper.text() ).toContain( 'Test Label' );
		} );

		it( 'renders multiple text fields correctly', () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'Text1', 'Text2' )
			} );
			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 2 );
		} );
	} );

	describe( 'Field manipulation', () => {
		it( 'adds new text field when add button is clicked', async () => {
			const wrapper = createWrapper();
			const addButton = wrapper.find( 'button.add-text-button' );
			await addButton.trigger( 'click' );
			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
			expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ][ 0 ] ).toEqual( newStringValue( 'Test', '' ) );
		} );

		it( 'removes text field when delete button is clicked', async () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'Text1', 'Text2' )
			} );
			await wrapper.findAll( '.delete-button' )[ 0 ].trigger( 'click' );
			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
		} );
	} );

	describe( 'Validation', () => {
		it( 'allows empty fields in multi-field setup even when required', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { required: true } ),
				modelValue: newStringValue( 'Text1', '' )
			} );
			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );
			const fields = wrapper.findAllComponents( CdxField );
			expect( fields[ 0 ].props( 'status' ) ).toBe( 'success' );
			expect( fields[ 0 ].props( 'messages' ) ).toEqual( {} );
		} );

		it( 'validates minimum length in multiple fields', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 5 } ),
				modelValue: newStringValue( 'Text1', '' )
			} );
			await wrapper.findAll( 'input' )[ 1 ].setValue( '1234' );
			const fields = wrapper.findAllComponents( CdxField );
			expect( fields[ 1 ].props( 'status' ) ).toBe( 'error' );
			expect( fields[ 1 ].props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-length' );
		} );

		it( 'validates maximum length in multiple fields', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { maxLength: 5 } ),
				modelValue: newStringValue( 'Text1', '' )
			} );
			await wrapper.findAll( 'input' )[ 1 ].setValue( '123456' );
			const fields = wrapper.findAllComponents( CdxField );
			expect( fields[ 1 ].props( 'status' ) ).toBe( 'error' );
			expect( fields[ 1 ].props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-length' );
		} );

		it( 'emits validation events for field changes', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 3 } ),
				modelValue: newStringValue( 'Text1', '' )
			} );
			await wrapper.vm.onInput( 'Valid', 1 );
			expect( wrapper.emitted( 'validation' )?.[ 0 ] ).toEqual( [ true ] );
			await wrapper.vm.onInput( '12', 1 );
			expect( wrapper.emitted( 'validation' )?.[ 1 ] ).toEqual( [ false ] );
		} );

		it( 'allows empty input for optional single field', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { required: false } ),
				modelValue: newStringValue( '' )
			} );
			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );
			const fields = wrapper.findAllComponents( CdxField );
			expect( fields[ 0 ].props( 'status' ) ).toBe( 'success' );
			expect( fields[ 0 ].props( 'messages' ) ).toEqual( {} );
		} );
	} );

	describe( 'Add button state', () => {
		it( 'disables add button when any text field is invalid', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 3 } ),
				modelValue: newStringValue( 'Valid', '' )
			} );
			await wrapper.findAll( 'input' )[ 1 ].setValue( '12' );
			const addButton = wrapper.find( 'button.add-text-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeDefined();
		} );

		it( 'enables add button when all text fields are valid', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 3 } ),
				modelValue: newStringValue( 'Valid', 'In' )
			} );
			await wrapper.findAll( 'input' )[ 1 ].setValue( 'Valid' );
			const addButton = wrapper.find( 'button.add-text-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeUndefined();
		} );
	} );
} );
