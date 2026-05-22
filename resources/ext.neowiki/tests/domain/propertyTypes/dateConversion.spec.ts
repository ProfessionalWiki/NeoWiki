import { describe, expect, it } from 'vitest';
import { fromDateInputValue, toDateInputValue } from '@/domain/propertyTypes/dateConversion';

describe( 'toDateInputValue', () => {
	it( 'returns empty string for undefined', () => {
		expect( toDateInputValue( undefined ) ).toBe( '' );
	} );

	it( 'returns empty string for an empty string', () => {
		expect( toDateInputValue( '' ) ).toBe( '' );
	} );

	it( 'returns empty string for an unparseable input', () => {
		expect( toDateInputValue( 'not-a-date' ) ).toBe( '' );
	} );

	it( 'returns empty string for a value carrying a time component', () => {
		expect( toDateInputValue( '2025-06-15T12:00:00Z' ) ).toBe( '' );
	} );

	it( 'returns the date unchanged for a valid YYYY-MM-DD input', () => {
		expect( toDateInputValue( '2025-06-15' ) ).toBe( '2025-06-15' );
	} );
} );

describe( 'fromDateInputValue', () => {
	it( 'returns undefined for empty string', () => {
		expect( fromDateInputValue( '' ) ).toBeUndefined();
	} );

	it( 'returns undefined for unparseable input', () => {
		expect( fromDateInputValue( 'garbage' ) ).toBeUndefined();
	} );

	it( 'returns undefined for a calendar overflow', () => {
		expect( fromDateInputValue( '2025-02-30' ) ).toBeUndefined();
	} );

	it( 'returns the date unchanged for a valid YYYY-MM-DD value', () => {
		expect( fromDateInputValue( '2025-06-15' ) ).toBe( '2025-06-15' );
	} );
} );

describe( 'round-trip preserves the date', () => {
	it.each( [
		'2025-06-15',
		'2024-02-29',
		'2030-12-31',
		'2000-01-01',
	] )( 'preserves the date for %s', ( date ) => {
		const wire = toDateInputValue( date );
		const result = fromDateInputValue( wire );

		expect( result ).toBe( date );
	} );
} );
