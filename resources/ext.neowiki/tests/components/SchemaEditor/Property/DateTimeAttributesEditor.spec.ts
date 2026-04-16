import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DateTimeAttributesEditor from '@/components/SchemaEditor/Property/DateTimeAttributesEditor.vue';
import { newDateTimeProperty, DateTimeProperty } from '@/domain/propertyTypes/DateTime';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper } from '../../../VueTestHelpers.ts';

describe( 'DateTimeAttributesEditor', () => {
	function newWrapper( props: Partial<AttributesEditorProps<DateTimeProperty>> = {} ): VueWrapper {
		return createTestWrapper( DateTimeAttributesEditor, {
			property: newDateTimeProperty( {} ),
			...props,
		} );
	}

	function getInputs( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement>[] {
		return wrapper.findAll<HTMLInputElement>( 'input[type="datetime-local"]' );
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
