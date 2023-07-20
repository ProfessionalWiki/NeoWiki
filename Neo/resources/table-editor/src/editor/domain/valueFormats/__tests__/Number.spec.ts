import { test, expect, describe, it } from 'vitest';
import { isValidNumber, newNumberProperty, NumberFormat } from '@/editor/domain/valueFormats/Number';
import { newNumberValue, newStringValue, type NumberValue } from '@/editor/domain/Value';

test.each( [
	[ '-.123.', false ],
	[ '-123', true ],
	[ '-123.45', true ],
	[ '-0.123', true ],
	[ '0', true ],
	[ '001', true ],
	[ '1', true ],
	[ '12 34', false ],
	[ '12a34', false ],
	[ '123', true ],
	[ '123.4', true ],
	[ '123.4.5', false ],
	[ '123.45.67', false ],
	[ '123..45', false ],
	[ '123.-45', false ],
	[ '123abc', false ],
	[ 'abc123', false ],
	[ ' abc1 ', false ],
	[ ' 123 ', true ],
	[ '.123.', false ]
] )( 'isValidNumber should return %s for number: %s', ( number: string, expected: boolean ) => {
	const result = isValidNumber( number );
	expect( result ).toBe( expected );
} );

describe( 'formatValueAsHtml', () => {

	it( 'returns the number', () => {
		const property = newNumberProperty();
		const value = newNumberValue( 123 );

		const html = ( new NumberFormat() ).formatValueAsHtml( value, property );

		expect( html ).toBe( '123' );
	} );

	it( 'returns the number with precision', () => {
		const property = { ...newNumberProperty(), precision: 2 };
		const value = newNumberValue( 123.45 );

		const html = ( new NumberFormat() ).formatValueAsHtml( value, property );

		expect( html ).toBe( '123.45' );
	} );

	it( 'returns additional fraction digits if the value has no fraction part', () => {
		const property = { ...newNumberProperty(), precision: 2 };
		const value = newNumberValue( 123 );

		const html = ( new NumberFormat() ).formatValueAsHtml( value, property );

		expect( html ).toBe( '123.00' );
	} );

	it( 'returns additional fraction digits if the precision is larger', () => {
		const property = { ...newNumberProperty(), precision: 2 };
		const value = newNumberValue( 123.9 );

		const html = ( new NumberFormat() ).formatValueAsHtml( value, property );

		expect( html ).toBe( '123.90' );
	} );

	it( 'returns rounded fraction digits if the precision is lower', () => {
		const property = { ...newNumberProperty(), precision: 3 };
		const value = newNumberValue( 123.45678 );

		const html = ( new NumberFormat() ).formatValueAsHtml( value, property );

		expect( html ).toBe( '123.457' );
	} );

	it( 'returns an empty string if the value is not a NumberValue', () => {
		const property = newNumberProperty();
		const value = newStringValue( '123' ) as unknown as NumberValue;

		const html = ( new NumberFormat() ).formatValueAsHtml( value, property );

		expect( html ).toBe( '' );
	} );

} );
