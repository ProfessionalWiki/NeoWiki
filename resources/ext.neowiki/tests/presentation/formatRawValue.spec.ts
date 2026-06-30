import { describe, expect, it } from 'vitest';
import { formatRawValue } from '@/presentation/formatRawValue';

describe( 'formatRawValue', () => {

	it( 'returns a string value unchanged', () => {
		expect( formatRawValue( '#ff0000' ) ).toBe( '#ff0000' );
	} );

	it( 'stringifies a number', () => {
		expect( formatRawValue( 42 ) ).toBe( '42' );
	} );

	it( 'stringifies a boolean', () => {
		expect( formatRawValue( false ) ).toBe( 'false' );
	} );

	it( 'JSON-encodes an object', () => {
		expect( formatRawValue( { hex: '#fff' } ) ).toBe( '{"hex":"#fff"}' );
	} );

	it( 'JSON-encodes an array', () => {
		expect( formatRawValue( [ 'a', 'b' ] ) ).toBe( '["a","b"]' );
	} );

	it( 'returns an empty string for null', () => {
		expect( formatRawValue( null ) ).toBe( '' );
	} );

	it( 'returns an empty string for undefined', () => {
		expect( formatRawValue( undefined ) ).toBe( '' );
	} );

} );
