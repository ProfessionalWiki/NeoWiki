import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxTextInput } from '@wikimedia/codex';
import DateAttributesEditor from '@/components/SchemaEditor/Property/DateAttributesEditor.vue';
import { newDateProperty, DateProperty } from '@/domain/propertyTypes/Date';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'DateAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-min-exceeds-max': 'Minimum cannot exceed maximum.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<DateProperty>> = {} ): VueWrapper {
		return createTestWrapper( DateAttributesEditor, {
			property: newDateProperty( {} ),
			...props,
		} );
	}

	function getInputs( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement>[] {
		return wrapper.findAll<HTMLInputElement>( 'input[type="date"]' );
	}

	function getMinimumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.date-attributes__minimum' ) as VueWrapper ).props() as FieldProps;
	}

	function getMaximumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.date-attributes__maximum' ) as VueWrapper ).props() as FieldProps;
	}

	describe( 'rendering', () => {
		it( 'renders two CdxTextInput components with date input-type', () => {
			const wrapper = newWrapper();

			const textInputs = wrapper.findAllComponents( CdxTextInput );
			expect( textInputs.length ).toBe( 2 );
			expect( textInputs[ 0 ].props( 'inputType' ) ).toBe( 'date' );
			expect( textInputs[ 1 ].props( 'inputType' ) ).toBe( 'date' );
		} );
	} );

	describe( 'displaying existing values', () => {
		it( 'renders minimum and maximum as the stored date strings', () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2020-01-01', maximum: '2030-12-31' } ),
			} );
			const inputs = getInputs( wrapper );

			expect( inputs[ 0 ].element.value ).toBe( '2020-01-01' );
			expect( inputs[ 1 ].element.value ).toBe( '2030-12-31' );
		} );

		it( 'displays empty inputs when minimum and maximum are undefined', () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );

			expect( inputs[ 0 ].element.value ).toBe( '' );
			expect( inputs[ 1 ].element.value ).toBe( '' );
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
				property: newDateProperty( { maximum: '2020-01-01' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2030-01-01' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinimumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field when max is less than min', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2030-01-01' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '2020-01-01' );

			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020-01-01' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2020-01-01' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '2020-01-01' } ] );
		} );

		it( 'clears min error when valid value resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2020-01-01' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2030-01-01' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].setValue( '2010-01-01' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'clears max error when valid min resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2030-01-01' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '2020-01-01' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].setValue( '2010-01-01' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'emitting updates', () => {
		it( 'emits minimum as the typed date string', async () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2020-01-01' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '2020-01-01' } ] );
		} );

		it( 'emits maximum as the typed date string', async () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '2030-12-31' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: '2030-12-31' } ] );
		} );

		it( 'emits undefined minimum when the min input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { minimum: '2020-01-01' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined } ] );
		} );

		it( 'emits undefined maximum when the max input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { maximum: '2030-12-31' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: undefined } ] );
		} );
	} );
} );
