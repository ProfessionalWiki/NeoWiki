import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { CdxTextInput } from '@wikimedia/codex';
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

	describe( 'rendering', () => {
		it( 'renders two CdxTextInput components with datetime-local input-type', () => {
			const wrapper = newWrapper();

			const textInputs = wrapper.findAllComponents( CdxTextInput );
			expect( textInputs.length ).toBe( 2 );
			expect( textInputs[ 0 ].props( 'inputType' ) ).toBe( 'datetime-local' );
			expect( textInputs[ 1 ].props( 'inputType' ) ).toBe( 'datetime-local' );
		} );
	} );

	describe( 'displaying existing values', () => {
		it( 'renders minimum and maximum as host-local wall-clock for the prop ISOs', () => {
			// Use minute-aligned ISOs so the host-local wall-clock (minute precision)
			// parses back to the exact same instant as the original UTC prop.
			const minimum = '2020-01-01T00:00:00Z';
			const maximum = '2030-12-31T23:59:00Z';
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { minimum, maximum } ),
			} );
			const inputs = getInputs( wrapper );

			expect( new Date( inputs[ 0 ].element.value ).getTime() ).toBe( new Date( minimum ).getTime() );
			expect( new Date( inputs[ 1 ].element.value ).getTime() ).toBe( new Date( maximum ).getTime() );
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
			const localValue = '2020-01-01T00:00';
			const maxIso = new Date( localValue ).toISOString();
			const wrapper = newWrapper( {
				property: newDateTimeProperty( { maximum: maxIso } ),
			} );
			const inputs = getInputs( wrapper );

			await inputs[ 0 ].setValue( localValue );

			expect( getMinimumFieldProps( wrapper ).status ).toBe( 'default' );
			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: maxIso } ] );
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
		it( 'emits minimum as the UTC ISO representing the typed local instant', async () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );
			const local = '2020-01-01T00:00';
			const expectedIso = new Date( local ).toISOString();

			await inputs[ 0 ].setValue( local );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { minimum: expectedIso } ] );
		} );

		it( 'emits maximum as the UTC ISO representing the typed local instant', async () => {
			const wrapper = newWrapper();
			const inputs = getInputs( wrapper );
			const local = '2030-12-31T23:59';
			const expectedIso = new Date( local ).toISOString();

			await inputs[ 1 ].setValue( local );

			expect( wrapper.emitted( 'update:property' )?.[ 0 ] ).toEqual( [ { maximum: expectedIso } ] );
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
