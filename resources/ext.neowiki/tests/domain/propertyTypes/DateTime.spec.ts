import { describe, expect, it } from 'vitest';
import {
	newDateTimeProperty,
	DateTimeType,
	formatDateTimeForDisplay,
	parseStrictDateTime,
} from '@/domain/propertyTypes/DateTime';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';

describe( 'DateTimeType', () => {

	it( 'returns no display attributes', () => {
		expect( new DateTimeType().getDisplayAttributeNames() ).toEqual( [] );
	} );

} );

describe( 'newDateTimeProperty', () => {

	it( 'creates property with default values when no options provided', () => {
		const property = newDateTimeProperty();

		expect( property.name ).toEqual( new PropertyName( 'DateTime' ) );
		expect( property.type ).toBe( DateTimeType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );

	it( 'creates property with custom name', () => {
		const property = newDateTimeProperty( { name: 'BirthDate' } );

		expect( property.name ).toEqual( new PropertyName( 'BirthDate' ) );
	} );

	it( 'creates property with all optional fields', () => {
		const property = newDateTimeProperty( {
			name: 'EventDate',
			description: 'When the event occurred',
			required: true,
			default: newStringValue( '2026-01-01T00:00:00Z' ),
			minimum: '2020-01-01T00:00:00Z',
			maximum: '2030-12-31T23:59:59Z',
		} );

		expect( property.name ).toEqual( new PropertyName( 'EventDate' ) );
		expect( property.description ).toBe( 'When the event occurred' );
		expect( property.required ).toBe( true );
		expect( property.minimum ).toBe( '2020-01-01T00:00:00Z' );
		expect( property.maximum ).toBe( '2030-12-31T23:59:59Z' );
	} );

} );

describe( 'createPropertyDefinitionFromJson', () => {
	const dateTimeType = new DateTimeType();

	it( 'normalizes null minimum and maximum to undefined', () => {
		// The PHP serializer emits these as null when unset; null must not leak into the string|undefined fields.
		const property = dateTimeType.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'DateTime' ), type: 'dateTime', description: '', required: false },
			{ type: 'dateTime', minimum: null, maximum: null },
		);

		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );
} );

describe( 'parseStrictDateTime', () => {

	it( 'returns a millisecond timestamp for a valid ISO with Z offset', () => {
		const result = parseStrictDateTime( '2025-06-15T12:00:00Z' );

		expect( result ).toBe( Date.parse( '2025-06-15T12:00:00Z' ) );
	} );

	it( 'returns a millisecond timestamp for a valid ISO with explicit numeric offset', () => {
		const result = parseStrictDateTime( '2025-06-15T23:30:00+05:00' );

		expect( result ).toBe( Date.parse( '2025-06-15T23:30:00+05:00' ) );
	} );

	it( 'returns null for a calendar-overflow date that Date silently rolls over', () => {
		expect( parseStrictDateTime( '2025-02-30T00:00:00Z' ) ).toBeNull();
	} );

	it( 'returns null for an ISO without an explicit offset or Z', () => {
		expect( parseStrictDateTime( '2025-06-15T12:00:00' ) ).toBeNull();
	} );

	it( 'returns null for completely malformed input', () => {
		expect( parseStrictDateTime( 'not-a-date' ) ).toBeNull();
	} );

} );

describe( 'formatDateTimeForDisplay', () => {

	it( 'returns the raw input when the ISO cannot be parsed', () => {
		expect( formatDateTimeForDisplay( 'not-a-date' ) ).toBe( 'not-a-date' );
	} );

	it( 'returns the raw input when given an empty string', () => {
		expect( formatDateTimeForDisplay( '' ) ).toBe( '' );
	} );

	it( 'renders a parsed ISO as a non-ISO human-readable string', () => {
		const result = formatDateTimeForDisplay( '2025-06-15T12:00:00Z' );

		expect( result ).not.toBe( '2025-06-15T12:00:00Z' );
		expect( result ).not.toMatch( /Z$/ );
		expect( result ).not.toMatch( /\d{4}-\d{2}-\d{2}T/ );
	} );

	it( 'includes the year of the parsed instant', () => {
		expect( formatDateTimeForDisplay( '2025-06-15T12:00:00Z' ) ).toContain( '2025' );
	} );

	it( 'includes a timezone abbreviation in the rendered text', () => {
		// With timeZoneName: 'short', toLocaleString appends a TZ abbreviation
		// (UTC, CEST, PDT, MEZ, ...) or a GMT±HH:MM offset. Both are 3+
		// consecutive uppercase letters, which won't match "Jun" (mixed case)
		// or AM/PM (2 letters).
		expect( formatDateTimeForDisplay( '2025-06-15T12:00:00Z' ) ).toMatch( /[A-Z]{3,}/ );
	} );

} );
