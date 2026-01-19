import { mount } from '@vue/test-utils';
import { describe, expect, it, test } from 'vitest';
import NumberDisplay from '@/components/Value/NumberDisplay.vue';
import { newNumberValue, newStringValue } from '@/domain/Value';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

function createWrapperWithNumber( number: number ): ReturnType<typeof mount> {
	return createWrapper( { value: newNumberValue( number ) } );
}

function createWrapper( props: Partial<ValueDisplayProps<NumberProperty>> ): ReturnType<typeof mount> {
	const defaultProps: ValueDisplayProps<NumberProperty> = {
		value: newNumberValue( 0 ),
		property: newNumberProperty(),
	};

	return mount( NumberDisplay, {
		props: {
			...defaultProps,
			...props,
		},
	} );
}

describe( 'NumberDisplay', () => {
	it( 'handles integers without precision', () => {
		const wrapper = createWrapperWithNumber( 30 );

		expect( wrapper.text() ).toBe( '30' );
	} );

	it( 'handles decimal numbers', () => {
		const wrapper = createWrapperWithNumber( 19.99 );

		expect( wrapper.text() ).toBe( '19.99' );
	} );

	it( 'handles negative numbers', () => {
		const wrapper = createWrapperWithNumber( -5 );

		expect( wrapper.text() ).toBe( '-5' );
	} );

	test.each( [
		[ 3.14159, 2, '3.14' ],
		[ 3.14159, 0, '3' ],
		[ 3.14, 5, '3.14000' ],

		[ -3.14159, 2, '-3.14' ],
		[ -3.14159, 0, '-3' ],
		[ -3.14, 5, '-3.14000' ],

		[ 0, 0, '0' ],
		[ 0, 3, '0.000' ],
		[ -0, 2, '0.00' ],

		// Rounding
		[ 0.51, 0, '1' ],
		[ 9999999.99, 1, '10000000.0' ],
		[ 0.000001, 4, '0.0000' ],
	] )( '%s with precision %s should be %s', ( number: number, precision: number, expected: string ): void => {
		const wrapper = createWrapper( {
			value: newNumberValue( number ),
			property: newNumberProperty( { precision: precision } ),
		} );

		expect( wrapper.text() ).toBe( expected );
	} );

	it( 'returns empty string for wrong value type', () => {
		const wrapper = mount( NumberDisplay, {
			props: {
				value: newStringValue( 'not a number' ),
				property: {} as NumberProperty,
			},
		} );
		expect( wrapper.text() ).toBe( '' );
	} );
} );
