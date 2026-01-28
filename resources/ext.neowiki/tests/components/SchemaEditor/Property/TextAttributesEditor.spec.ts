import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxTextInput, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import { newTextProperty, TextProperty } from '@/domain/propertyTypes/Text';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, mockMwMessage } from '../../../VueTestHelpers.ts';

interface FieldProps {
	status: ValidationStatusType;
	messages: ValidationMessages;
}

describe( 'TextAttributesEditor', () => {
	beforeEach( () => {
		mockMwMessage( {
			'neowiki-property-editor-length-whole-number': 'Must be a whole number of at least 1.',
			'neowiki-property-editor-length-min-exceeds-max': 'Minimum cannot exceed maximum.',
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<TextProperty>> = {} ): VueWrapper {
		return createTestWrapper( TextAttributesEditor, {
			property: newTextProperty( {} ),
			...props,
		} );
	}

	function getLengthFieldProps( wrapper: VueWrapper ): FieldProps {
		const fields = wrapper.findAllComponents( CdxField );
		// Length constraint is the last CdxField (after toggle switches)
		return fields[ fields.length - 1 ].props() as FieldProps;
	}

	describe( 'displaying existing values', () => {
		it( 'displays existing minLength and maxLength', () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { minLength: 5, maxLength: 100 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( '5' );
			expect( inputs[ 1 ].props( 'modelValue' ) ).toBe( '100' );
		} );

		it( 'displays existing multiple and uniqueItems', () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { multiple: true, uniqueItems: false } ),
			} );
			const toggles = wrapper.findAll( 'input[type="checkbox"]' );

			expect( ( toggles[ 0 ].element as HTMLInputElement ).checked ).toBe( true );
			expect( ( toggles[ 1 ].element as HTMLInputElement ).checked ).toBe( false );
		} );
	} );

	describe( 'length constraint validation', () => {
		it( 'shows no error when both fields are empty', () => {
			const wrapper = newWrapper();

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows no error for valid positive integers', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '5' );
			expect( getLengthFieldProps( wrapper ).status ).toBe( 'default' );

			await inputs[ 1 ].vm.$emit( 'update:model-value', '100' );
			expect( getLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows error and does not emit for zero', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '0' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error and does not emit for negative numbers', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '-5' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error and does not emit for decimal numbers', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:model-value', '5.5' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error and does not emit for non-numeric input', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', 'abc' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error when min exceeds max', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { maxLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '20' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error when max is less than min', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { minLength: 20 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:model-value', '10' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { maxLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '10' );

			expect( getLengthFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		} );

		it( 'clears error when valid value is entered after invalid', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '-5' );
			expect( getLengthFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '5' );
			expect( getLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits minLength when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '10' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minLength: 10 } ] );
		} );

		it( 'emits maxLength when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:model-value', '50' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maxLength: 50 } ] );
		} );

		it( 'emits undefined when field is cleared', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { minLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:model-value', '' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minLength: undefined } ] );
		} );

		it( 'emits update when multiple is toggled', async () => {
			const wrapper = newWrapper();

			await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { multiple: true } ] );
		} );
	} );
} );
