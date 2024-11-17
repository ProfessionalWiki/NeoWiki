import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TextInput from '@/components/Value/TextInput.vue';
import { CdxField, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';
import { newTextProperty } from '@neo/domain/valueFormats/Text';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'TextInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function createWrapper( props: Partial<InstanceType<typeof TextInput>['$props']> = {} ): VueWrapper<InstanceType<typeof TextInput>> {
		return createTestWrapper( TextInput, {
			modelValue: newStringValue( 'Test' ),
			label: 'Test Label',
			property: newTextProperty( { multiple: true } ),
			...props
		} );
	}

	const assertFieldStatus = (
		field: VueWrapper<InstanceType<typeof CdxField>>,
		expectedStatus: ValidationStatusType,
		expectedErrorMessage: ValidationMessages = {}
	): void => {
		expect( field.props( 'status' ) ).toBe( expectedStatus );
		expect( field.props( 'messages' ) ).toEqual( {
			...expectedErrorMessage
		} );
	};

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

	describe( 'Adding and Deleting Text Values', () => {
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
		it( 'does not allow all empty fields when property value is required', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { required: true, multiple: true } ),
				modelValue: newStringValue( 'Text1', 'Text2' )
			} );
			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );
			await wrapper.findAll( 'input' )[ 1 ].setValue( '' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 0 ], 'error', { error: 'neowiki-field-required' } );
		} );

		it( 'validates minimum length in multiple fields', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 5 } ),
				modelValue: newStringValue( 'Text1', '12345' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( '1234' );
			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 1 ], 'error', { error: 'neowiki-field-min-length' } );
		} );

		it( 'validates maximum length in multiple fields', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { maxLength: 5 } ),
				modelValue: newStringValue( 'Text1', '12345' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( '123456' );
			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 1 ], 'error', { error: 'neowiki-field-max-length' } );
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
			assertFieldStatus( fields[ 0 ], 'success' );
		} );
	} );

	describe( 'Add button state', () => {
		it( 'disables add button when any text field is invalid', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 3, multiple: true } ),
				modelValue: newStringValue( 'Valid', '123' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'in' );
			const addButton = wrapper.find( 'button.add-text-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeDefined();
		} );

		it( 'enables add button when all text fields are valid', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 8, multiple: true } ),
				modelValue: newStringValue( 'ValidValue', 'InValid' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'Valid-value' );
			const addButton = wrapper.find( 'button.add-text-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeUndefined();
		} );
	} );
} );
