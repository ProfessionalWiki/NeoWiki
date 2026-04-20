import { describe, expect, it } from 'vitest';
import { fromLocalInputValue, toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion';

describe( 'toLocalInputValue', () => {
	it( 'returns empty string for undefined', () => {
		expect( toLocalInputValue( undefined ) ).toBe( '' );
	} );

	it( 'returns empty string for an unparseable input', () => {
		expect( toLocalInputValue( 'not-a-date' ) ).toBe( '' );
	} );

	it( 'returns a YYYY-MM-DDTHH:mm string of length 16 for a valid ISO input', () => {
		const result = toLocalInputValue( '2025-06-15T12:00:00Z' );

		expect( result.length ).toBe( 16 );
		expect( result ).toMatch( /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/ );
	} );
} );

describe( 'fromLocalInputValue', () => {
	it( 'returns undefined for empty string', () => {
		expect( fromLocalInputValue( '' ) ).toBeUndefined();
	} );

	it( 'returns undefined for unparseable input', () => {
		expect( fromLocalInputValue( 'garbage' ) ).toBeUndefined();
	} );

	it( 'encodes a datetime-local value as UTC ISO corresponding to that local instant', () => {
		const local = '2025-06-15T14:00';
		const expectedIso = new Date( local ).toISOString();

		expect( fromLocalInputValue( local ) ).toBe( expectedIso );
	} );
} );

describe( 'round-trip preserves the instant', () => {
	it.each( [
		'2025-06-15T12:00:00Z',
		'2025-06-15T23:30:00+05:00',
		'2025-06-15T03:15:00-08:00',
		'2025-12-31T23:00:00Z',
	] )( 'preserves the instant for %s', ( iso ) => {
		const local = toLocalInputValue( iso );
		const result = fromLocalInputValue( local );

		expect( result ).toBeDefined();
		expect( new Date( result! ).getTime() ).toBe( new Date( iso ).getTime() );
	} );
} );
