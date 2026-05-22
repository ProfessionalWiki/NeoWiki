import { describe, expect, it } from 'vitest';
import {
	newDateProperty,
	DateType,
	formatDateForDisplay,
	parseStrictDate,
} from '@/domain/propertyTypes/Date';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';

describe( 'DateType', () => {

	it( 'returns no display attributes', () => {
		expect( new DateType().getDisplayAttributeNames() ).toEqual( [] );
	} );

} );

describe( 'newDateProperty', () => {

	it( 'creates property with default values when no options provided', () => {
		const property = newDateProperty();

		expect( property.name ).toEqual( new PropertyName( 'Date' ) );
		expect( property.type ).toBe( DateType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );

	it( 'creates property with custom name', () => {
		const property = newDateProperty( { name: 'BirthDate' } );

		expect( property.name ).toEqual( new PropertyName( 'BirthDate' ) );
	} );

	it( 'creates property with all optional fields', () => {
		const property = newDateProperty( {
			name: 'EventDate',
			description: 'When the event occurred',
			required: true,
			default: newStringValue( '2026-01-01' ),
			minimum: '2020-01-01',
			maximum: '2030-12-31',
		} );

		expect( property.name ).toEqual( new PropertyName( 'EventDate' ) );
		expect( property.description ).toBe( 'When the event occurred' );
		expect( property.required ).toBe( true );
		expect( property.minimum ).toBe( '2020-01-01' );
		expect( property.maximum ).toBe( '2030-12-31' );
	} );

} );

describe( 'validate', () => {
	const dateType = new DateType();

	it( 'returns no errors for undefined value when optional', () => {
		const property = newDateProperty( { required: false } );

		expect( dateType.validate( undefined, property ) ).toEqual( [] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newDateProperty( { required: true } );

		expect( dateType.validate( undefined, property ) ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors for valid date within bounds', () => {
		const property = newDateProperty( {
			minimum: '2020-01-01',
			maximum: '2030-12-31',
		} );

		expect( dateType.validate( newStringValue( '2025-06-15' ), property ) ).toEqual( [] );
	} );

	it( 'returns invalid-date error for unparseable string', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( 'not-a-date' ), property ) ).toEqual( [
			{ code: 'invalid-date' },
		] );
	} );

	it( 'returns invalid-date error for year-only string', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( '2025' ), property ) ).toEqual( [
			{ code: 'invalid-date' },
		] );
	} );

	it( 'returns invalid-date error for year-month string', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( '2025-06' ), property ) ).toEqual( [
			{ code: 'invalid-date' },
		] );
	} );

	it( 'returns invalid-date error for a value carrying a time component', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( '2025-06-15T12:00:00Z' ), property ) ).toEqual( [
			{ code: 'invalid-date' },
		] );
	} );

	it( 'returns invalid-date error for overflowing calendar date', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( '2025-02-30' ), property ) ).toEqual( [
			{ code: 'invalid-date' },
		] );
	} );

	it( 'returns invalid-date error for Feb 29 in a non-leap year', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( '2025-02-29' ), property ) ).toEqual( [
			{ code: 'invalid-date' },
		] );
	} );

	it( 'accepts Feb 29 in a leap year', () => {
		const property = newDateProperty();

		expect( dateType.validate( newStringValue( '2024-02-29' ), property ) ).toEqual( [] );
	} );

	it( 'returns min-value error when before minimum', () => {
		const property = newDateProperty( { minimum: '2025-01-01' } );

		expect( dateType.validate( newStringValue( '2024-12-31' ), property ) ).toEqual( [
			{ code: 'min-value', args: [ '2025-01-01' ] },
		] );
	} );

	it( 'returns no errors one day after minimum', () => {
		const property = newDateProperty( { minimum: '2025-01-01' } );

		expect( dateType.validate( newStringValue( '2025-01-02' ), property ) ).toEqual( [] );
	} );

	it( 'returns max-value error when after maximum', () => {
		const property = newDateProperty( { maximum: '2025-12-31' } );

		expect( dateType.validate( newStringValue( '2026-01-01' ), property ) ).toEqual( [
			{ code: 'max-value', args: [ '2025-12-31' ] },
		] );
	} );

	it( 'returns no errors one day before maximum', () => {
		const property = newDateProperty( { maximum: '2025-12-31' } );

		expect( dateType.validate( newStringValue( '2025-12-30' ), property ) ).toEqual( [] );
	} );

	it( 'returns no errors for date equal to bounds (inclusive min and max)', () => {
		const property = newDateProperty( {
			minimum: '2025-06-15',
			maximum: '2025-06-15',
		} );

		expect( dateType.validate( newStringValue( '2025-06-15' ), property ) ).toEqual( [] );
	} );

	it( 'returns no errors when value is empty because newStringValue strips empty parts', () => {
		const property = newDateProperty( { required: false } );
		const emptyValue = newStringValue( '' );

		expect( emptyValue.parts ).toEqual( [] );
		expect( dateType.validate( emptyValue, property ) ).toEqual( [] );
	} );

	it( 'silently ignores a malformed minimum rather than rejecting the value', () => {
		const property = newDateProperty( { minimum: 'garbage' } );

		expect( dateType.validate( newStringValue( '2025-06-15' ), property ) ).toEqual( [] );
	} );

	it( 'silently ignores a malformed maximum rather than rejecting the value', () => {
		const property = newDateProperty( { maximum: 'garbage' } );

		expect( dateType.validate( newStringValue( '2025-06-15' ), property ) ).toEqual( [] );
	} );

} );

describe( 'parseStrictDate', () => {

	it( 'returns a UTC-midnight millisecond timestamp for a valid date', () => {
		const result = parseStrictDate( '2025-06-15' );

		expect( result ).toBe( Date.parse( '2025-06-15T00:00:00Z' ) );
	} );

	it( 'returns null for a calendar-overflow date that Date silently rolls over', () => {
		expect( parseStrictDate( '2025-02-30' ) ).toBeNull();
	} );

	it( 'returns null for a value with a time component', () => {
		expect( parseStrictDate( '2025-06-15T12:00:00Z' ) ).toBeNull();
	} );

	it( 'returns null for completely malformed input', () => {
		expect( parseStrictDate( 'not-a-date' ) ).toBeNull();
	} );

} );

describe( 'formatDateForDisplay', () => {

	it( 'returns the raw input when the value cannot be parsed', () => {
		expect( formatDateForDisplay( 'not-a-date' ) ).toBe( 'not-a-date' );
	} );

	it( 'returns the raw input when given an empty string', () => {
		expect( formatDateForDisplay( '' ) ).toBe( '' );
	} );

	it( 'renders a parsed date as a non-ISO human-readable string', () => {
		const result = formatDateForDisplay( '2025-06-15' );

		expect( result ).not.toBe( '2025-06-15' );
		expect( result ).not.toMatch( /^\d{4}-\d{2}-\d{2}$/ );
	} );

	it( 'includes the year of the date', () => {
		expect( formatDateForDisplay( '2025-06-15' ) ).toContain( '2025' );
	} );

	it( 'renders the stored calendar day regardless of host timezone', () => {
		// Interpreted as UTC and rendered with timeZone: 'UTC', so the day
		// component is always 15 and never rolls to 14 or 16.
		expect( formatDateForDisplay( '2025-06-15' ) ).toContain( '15' );
	} );

	it( 'does not append a time component', () => {
		expect( formatDateForDisplay( '2025-06-15' ) ).not.toMatch( /\d{2}:\d{2}/ );
	} );

} );
