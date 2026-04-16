import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import DateTimeAttributesEditor from '@/components/SchemaEditor/Property/DateTimeAttributesEditor.vue';
import { newDateTimeProperty, DateTimeProperty } from '@/domain/propertyTypes/DateTime';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper, FieldProps, setupMwMock } from '../../../VueTestHelpers.ts';

describe( 'DateTimeAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-property-editor-min-exceeds-max': 'Minimum cannot exceed maximum.',
			},
			functions: [ 'message' ],
		} );
	} );

	function newWrapper( props: Partial<AttributesEditorProps<DateTimeProperty>> = {} ): VueWrapper {
		return createTestWrapper( DateTimeAttributesEditor, {
			property: newDateTimeProperty( {} ),
			...props,
		} );
	}

	function getInputs( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement>[] {
		return wrapper.findAll<HTMLInputElement>( 'input[type="datetime-local"]' );
	}

	function getMinimumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.datetime-attributes__minimum' ) as VueWrapper ).props() as FieldProps;
	}

	function getMaximumFieldProps( wrapper: VueWrapper ): FieldProps {
		return ( wrapper.findComponent( '.datetime-attributes__maximum' ) as VueWrapper ).props() as FieldProps;
	}

	describe( 'displaying existing values', () => {
		// FIXME(#728): pins the current Z-stripping behavior; revisit when the timezone story is decided.
		it( 'strips the Z suffix and seconds from minimum and maximum for the native input', () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( {
					minimum: '2020-01-01T00:00:00Z',
					maximum: '2030-12-31T23:59:59Z',
				} ),
			} );
			const inputs = getInputs( wrapper );

			expect( inputs[ 0 ].element.value ).toBe( '2020-01-01T00:00' );
			expect( inputs[ 1 ].element.value ).toBe( '2030-12-31T23:59' );
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
				property: newDateTimeProperty( { maximum: '2020-01-01T00:00:00Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2030-01-01T00:00' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMinimumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'shows error on max field when max is less than min', async () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { minimum: '2030-01-01T00:00:00Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '2020-01-01T00:00' );

			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );
			expect( getMaximumFieldProps( wrapper ).messages ).toEqual( {
				error: 'Minimum cannot exceed maximum.',
			} );
			expect( wrapper.emitted( 'update:property' ) ).toBeFalsy();
		} );

		it( 'allows min equal to max', async () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { maximum: '2020-01-01T00:00:00Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2020-01-01T00:00' );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '2020-01-01T00:00:00Z' } ] );
		} );

		it( 'clears min error when valid value resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { maximum: '2020-01-01T00:00:00Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2030-01-01T00:00' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].setValue( '2010-01-01T00:00' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
		} );

		it( 'clears max error when valid min resolves conflict', async () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { minimum: '2030-01-01T00:00:00Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '2020-01-01T00:00' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'error' );

			await inputs[ 0 ].setValue( '2010-01-01T00:00' );
			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( getMaximumFieldProps( wrapper ).status ).toBe( 'default' );
		} );
	} );

	describe( 'emitting updates', () => {
		// FIXME(#728): pins the TZ-naive Z-suffix emission; revisit with the timezone fix.
		it( 'emits minimum as an ISO string with Z suffix when the min input changes', async () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '2020-01-01T00:00' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: '2020-01-01T00:00:00Z' } ] );
		} );

		// FIXME(#728): pins the TZ-naive Z-suffix emission; revisit with the timezone fix.
		it( 'emits maximum as an ISO string with Z suffix when the max input changes', async () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '2030-12-31T23:59' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: '2030-12-31T23:59:00Z' } ] );
		} );

		it( 'emits undefined minimum when the min input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { minimum: '2020-01-01T00:00:00Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( '' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: undefined } ] );
		} );

		it( 'emits undefined maximum when the max input is cleared', async () => {
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { maximum: '2030-12-31T23:59:59Z' } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 1 ].setValue( '' );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: undefined } ] );
		} );
	} );
} );
