import { describe, expect, it } from 'vitest';
import { minimumExcludesMaximum, parsePartialDate } from '@/domain/propertyTypes/PartialDate';

describe( 'parsePartialDate', () => {

	it( 'parses a year', () => {
		expect( parsePartialDate( '1984' ) ).toEqual( { precision: 'year', year: 1984 } );
	} );

	it( 'parses a year and month', () => {
		expect( parsePartialDate( '1984-06' ) ).toEqual( { precision: 'month', year: 1984, month: 6 } );
	} );

	it( 'parses a full date', () => {
		expect( parsePartialDate( '1984-06-15' ) ).toEqual( { precision: 'day', year: 1984, month: 6, day: 15 } );
	} );

	it( 'accepts the leap day of a leap year', () => {
		expect( parsePartialDate( '1984-02-29' ) ).not.toBeNull();
	} );

	it.each( [
		[ 'a time component', '2025-06-15T12:00:00Z' ],
		[ 'an invalid month', '2025-13' ],
		[ 'a calendar overflow', '2025-02-30' ],
		[ 'the leap day of a common year', '2025-02-29' ],
		[ 'an unpadded month', '2025-6' ],
		[ 'a two digit year', '84' ],
		[ 'year zero', '0000' ],
		[ 'a negative year', '-0044' ],
		[ 'a trailing dash', '2025-' ],
		[ 'surrounding whitespace', ' 2025 ' ],
		[ 'garbage', 'not-a-date' ],
		[ 'an empty string', '' ],
	] )( 'returns null for %s', ( _description: string, value: string ) => {
		expect( parsePartialDate( value ) ).toBeNull();
	} );

} );

describe( 'minimumExcludesMaximum', () => {

	it.each( [
		[ 'a later day', '2030-01-01', '2020-01-01' ],
		[ 'a year after the maximum year', '1991', '1990' ],
		[ 'a month after the maximum day', '1990-07', '1990-06-30' ],
		[ 'a day after the maximum month', '1990-07-01', '1990-06' ],
	] )( 'is true for %s', ( _description: string, minimum: string, maximum: string ) => {
		expect( minimumExcludesMaximum( minimum, maximum ) ).toBe( true );
	} );

	it.each( [
		[ 'equal days', '2025-06-15', '2025-06-15' ],
		[ 'a year holding the maximum day', '1990', '1990-06-30' ],
		[ 'a day inside the maximum year', '1990-12-31', '1990' ],
		[ 'a month inside the maximum year', '1990-12', '1990' ],
		[ 'an unparseable minimum', 'abc', '1990' ],
		[ 'an unparseable maximum', '1990', '' ],
	] )( 'is false for %s', ( _description: string, minimum: string, maximum: string ) => {
		expect( minimumExcludesMaximum( minimum, maximum ) ).toBe( false );
	} );

} );
