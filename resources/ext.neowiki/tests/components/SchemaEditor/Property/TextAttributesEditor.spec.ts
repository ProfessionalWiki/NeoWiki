import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { newTextProperty, TextProperty } from '@/domain/propertyTypes/Text';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'TextAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-length-whole-number': 'Must be a whole number of at least 1.',
				'neowiki-property-editor-length-min-exceeds-max': 'Minimum cannot exceed maximum.',
			},
			functions: [ 'config', 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<TextProperty>> = {} ): VueWrapper {
		return createTestWrapper( TextAttributesEditor, {
			property: newTextProperty( {} ),
			...props,
		} );
	}

	function getMinLengthFieldProps( wrapper: VueWrapper ): FieldProps {
		const fields = wrapper.findAllComponents( CdxField );
		return fields[ fields.length - 2 ].props() as FieldProps;
	}

	function getMaxLengthFieldProps( wrapper: VueWrapper ): FieldProps {
		const fields = wrapper.findAllComponents( CdxField );
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
			const checkboxes = wrapper.findAll( 'input[type="checkbox"]' );

			expect( ( checkboxes[ 0 ].element as HTMLInputElement ).checked ).toBe( true );
			expect( ( checkboxes[ 1 ].element as HTMLInputElement ).checked ).toBe( false );
		} );
	} );

	describe( 'length constraint validation', () => {
		it( 'shows no error when both fields are empty', () => {
			const wrapper = newWrapper();

			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaxLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows no error for valid positive integers', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'default' );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '100' );
			expect( getMaxLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'shows error on min field for zero', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '0' );

			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on min field for negative numbers', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '-5' );

			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field for decimal numbers', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '5.5' );

			expect( getMaxLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMaxLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on min field for non-numeric input', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', 'abc' );

			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Must be a whole number of at least 1.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on min field when min exceeds max', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { maxLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '20' );

			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field when max is less than min', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { minLength: 20 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '10' );

			expect( getMaxLengthFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMaxLengthFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { maxLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '10' );

			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
		} );

		it( 'clears error when valid value is entered after invalid', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '-5' );
			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'clears cross-field error on other field when valid value resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { maxLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '20' );
			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '5' );
			expect( getMinLengthFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaxLengthFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits minLength when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '10' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minLength: 10 } ] );
		} );

		it( 'emits maxLength when valid value is entered', async () => {
			const wrapper = newWrapper();
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 1 ].vm.$emit( 'update:modelValue', '50' );

			expect( wrapper.emitted( 'update:property' ) ).toBeTruthy();
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maxLength: 50 } ] );
		} );

		it( 'emits undefined when field is cleared', async () => {
			const wrapper = newWrapper( {
				property: newTextProperty( { minLength: 10 } ),
			} );
			const inputs = wrapper.findAllComponents( CdxTextInput );

			await inputs[ 0 ].vm.$emit( 'update:modelValue', '' );

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
	describe( 'Constraint severity', () => {
		function severityInputIn( wrapper: VueWrapper, selector: string ): VueWrapper<InstanceType<typeof SeverityInput>> {
			return ( wrapper.findComponent( selector ) as VueWrapper ).findComponent( SeverityInput );
		}

		it( 'shows the current severity of a set length bound', () => {
			const wrapper = newWrapper( {
				property: { ...newTextProperty( { maxLength: 40 } ), constraintSeverities: { maxLength: 'error' } },
			} );

			expect( severityInputIn( wrapper, '.text-attributes__max-length' ).props( 'modelValue' ) ).toBe( 'error' );
		} );

		it( 'offers no severity for an unset length bound', () => {
			const wrapper = newWrapper( { property: newTextProperty( { maxLength: 40 } ) } );

			expect( severityInputIn( wrapper, '.text-attributes__min-length' ).exists() ).toBe( false );
		} );

		it( 'emits the changed severity of a length bound, keeping the other bound\'s', async () => {
			const wrapper = newWrapper( {
				property: { ...newTextProperty( { minLength: 2, maxLength: 40 } ), constraintSeverities: { maxLength: 'error' } },
			} );

			await severityInputIn( wrapper, '.text-attributes__min-length' ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [
				[ { constraintSeverities: { maxLength: 'error', minLength: 'error' } } ],
			] );
		} );

		it( 'offers a severity for unique values while they are required', () => {
			const wrapper = newWrapper( { property: newTextProperty( { multiple: true, uniqueItems: true } ) } );

			expect( severityInputIn( wrapper, '.text-attributes__unique-items' ).exists() ).toBe( true );
		} );

		it( 'emits the changed severity of unique values', async () => {
			const wrapper = newWrapper( { property: newTextProperty( { multiple: true, uniqueItems: true } ) } );

			await severityInputIn( wrapper, '.text-attributes__unique-items' ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { constraintSeverities: { uniqueItems: 'error' } } ] ] );
		} );

		it( 'offers no severity for allowing multiple values, a shape field', () => {
			const wrapper = newWrapper( { property: newTextProperty( { multiple: true, uniqueItems: false } ) } );

			expect( wrapper.findAllComponents( SeverityInput ) ).toHaveLength( 0 );
		} );
	} );
} );
