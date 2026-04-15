import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxTextInput } from '@wikimedia/codex';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'NumberAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-precision-non-negative': 'Precision cannot be negative.',
				'neowiki-property-editor-min-exceeds-max': 'Minimum cannot exceed maximum.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<NumberProperty>> = {} ): VueWrapper {
		return createTestWrapper( NumberAttributesEditor, {
			property: newNumberProperty( {} ),
			...props,
		} );
	}

	function getMinimumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.number-attributes__minimum' ) as VueWrapper ).props() as FieldProps;
	}

	function getMaximumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.number-attributes__maximum' ) as VueWrapper ).props() as FieldProps;
	}

	function getPrecisionFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.number-attributes__precision' ) as VueWrapper ).props() as FieldProps;
	}

	describe( 'displaying existing values', () => {
		it( 'displays existing minimum, maximum and precision', () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { minimum: 5, maximum: 100, precision: 2 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			expect( inputs[ 0 ].props( 'modelValue' ) ).toBe( '5' );
			expect( inputs[ 1 ].props( 'modelValue' ) ).toBe( '100' );
			expect( inputs[ 2 ].props( 'modelValue' ) ).toBe( '2' );
		} );
	} );

	describe( 'range validation', () => {
		it( 'shows no error when both fields are empty', () => {
			const wrapper = newWrapper();

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows error on min field when min exceeds max', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { maximum: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '20' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinimumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field when max is less than min', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { minimum: 20 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '10' );

			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { maximum: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '10' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		} );

		it( 'clears min error when valid value resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { maximum: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '20' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'clears max error when valid min resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { minimum: 20 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '10' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'allows negative numbers for min and max', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '-10' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: -10 } ] );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '-5' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'precision validation', () => {
		it( 'shows error and does not emit when precision is negative', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 2 ].vm.$emit( 'update:modelValue', '-5' );

			expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getPrecisionFieldProps( wrapper ).messages ).toEqual( { error: 'Precision cannot be negative.' } );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'preserves invalid input in the precision field', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { precision: 2 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 2 ].vm.$emit( 'update:modelValue', '-5' );

			expect( inputs[ 2 ].props( 'modelValue' ) ).toBe( '-5' );
		} );

		it( 'shows no error when precision is zero or positive', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 2 ].vm.$emit( 'update:modelValue', '0' );
			expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'default' );

			await inputs[ 2 ].vm.$emit( 'update:modelValue', '5' );
			expect( getPrecisionFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits minimum when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '10' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: 10 } ] );
		} );

		it( 'emits maximum when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '50' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: 50 } ] );
		} );

		it( 'emits precision when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 2 ].vm.$emit( 'update:modelValue', '3' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { precision: 3 } ] );
		} );

		it( 'emits undefined when input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { minimum: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined } ] );
		} );
	} );
} );
