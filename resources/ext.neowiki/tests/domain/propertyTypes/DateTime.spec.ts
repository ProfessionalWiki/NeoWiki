import { describe, expect, it } from 'vitest';
import { newDateTimeProperty, DateTimeType, parseStrictDateTime } from '@/domain/propertyTypes/DateTime';
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

describe( 'validate', () => {
	const dateTimeType = new DateTimeType();

	it( 'returns no errors for undefined value when optional', () => {
		const property = newDateTimeProperty( { required: false } );

		expect( dateTimeType.validate( undefined, property ) ).toEqual( [] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newDateTimeProperty( { required: true } );

		expect( dateTimeType.validate( undefined, property ) ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors for valid datetime within bounds', () => {
		const property = newDateTimeProperty( {
			minimum: '2020-01-01T00:00:00Z',
			maximum: '2030-12-31T23:59:59Z',
		} );

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00Z' ), property ) ).toEqual( [] );
	} );

	it( 'returns invalid-datetime error for unparseable string', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( 'not-a-date' ), property ) ).toEqual( [
			{ code: 'invalid-datetime' },
		] );
	} );

	it( 'returns invalid-datetime error for year-only string', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025' ), property ) ).toEqual( [
			{ code: 'invalid-datetime' },
		] );
	} );

	it( 'returns invalid-datetime error for year-month string', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06' ), property ) ).toEqual( [
			{ code: 'invalid-datetime' },
		] );
	} );

	it( 'returns invalid-datetime error for date-only string', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06-15' ), property ) ).toEqual( [
			{ code: 'invalid-datetime' },
		] );
	} );

	it( 'returns invalid-datetime error for overflowing calendar date', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-02-30T00:00:00Z' ), property ) ).toEqual( [
			{ code: 'invalid-datetime' },
		] );
	} );

	it( 'returns invalid-datetime error when timezone offset is missing', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00' ), property ) ).toEqual( [
			{ code: 'invalid-datetime' },
		] );
	} );

	it( 'accepts explicit positive timezone offset', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00+02:00' ), property ) ).toEqual( [] );
	} );

	it( 'accepts explicit negative timezone offset', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00-05:00' ), property ) ).toEqual( [] );
	} );

	it( 'accepts fractional seconds with Z offset', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00.123Z' ), property ) ).toEqual( [] );
	} );

	it( 'accepts nanosecond-precision fractional seconds (9 digits)', () => {
		const property = newDateTimeProperty();

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00.123456789Z' ), property ) ).toEqual( [] );
	} );

	it( 'returns min-value error when before minimum', () => {
		const property = newDateTimeProperty( { minimum: '2025-01-01T00:00:00Z' } );

		expect( dateTimeType.validate( newStringValue( '2024-12-31T23:59:59Z' ), property ) ).toEqual( [
			{ code: 'min-value', args: [ '2025-01-01T00:00:00Z' ] },
		] );
	} );

	it( 'returns min-value error when one millisecond before minimum', () => {
		const property = newDateTimeProperty( { minimum: '2025-01-01T00:00:00.000Z' } );

		expect( dateTimeType.validate( newStringValue( '2024-12-31T23:59:59.999Z' ), property ) ).toEqual( [
			{ code: 'min-value', args: [ '2025-01-01T00:00:00.000Z' ] },
		] );
	} );

	it( 'returns no errors one millisecond after minimum', () => {
		const property = newDateTimeProperty( { minimum: '2025-01-01T00:00:00.000Z' } );

		expect( dateTimeType.validate( newStringValue( '2025-01-01T00:00:00.001Z' ), property ) ).toEqual( [] );
	} );

	it( 'returns max-value error when after maximum', () => {
		const property = newDateTimeProperty( { maximum: '2025-12-31T23:59:59Z' } );

		expect( dateTimeType.validate( newStringValue( '2026-01-01T00:00:00Z' ), property ) ).toEqual( [
			{ code: 'max-value', args: [ '2025-12-31T23:59:59Z' ] },
		] );
	} );

	it( 'returns max-value error when one millisecond after maximum', () => {
		const property = newDateTimeProperty( { maximum: '2025-12-31T23:59:59.999Z' } );

		expect( dateTimeType.validate( newStringValue( '2026-01-01T00:00:00.000Z' ), property ) ).toEqual( [
			{ code: 'max-value', args: [ '2025-12-31T23:59:59.999Z' ] },
		] );
	} );

	it( 'returns no errors one millisecond before maximum', () => {
		const property = newDateTimeProperty( { maximum: '2025-12-31T23:59:59.999Z' } );

		expect( dateTimeType.validate( newStringValue( '2025-12-31T23:59:59.998Z' ), property ) ).toEqual( [] );
	} );

	it( 'returns no errors for datetime equal to bounds (inclusive min and max)', () => {
		const property = newDateTimeProperty( {
			minimum: '2025-06-15T12:00:00Z',
			maximum: '2025-06-15T12:00:00Z',
		} );

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00Z' ), property ) ).toEqual( [] );
	} );

	it( 'returns no errors when value is empty because newStringValue strips empty parts', () => {
		const property = newDateTimeProperty( { required: false } );
		const emptyValue = newStringValue( '' );

		expect( emptyValue.parts ).toEqual( [] );
		expect( dateTimeType.validate( emptyValue, property ) ).toEqual( [] );
	} );

	it( 'silently ignores a malformed minimum rather than rejecting the value', () => {
		const property = newDateTimeProperty( { minimum: 'garbage' } );

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00Z' ), property ) ).toEqual( [] );
	} );

	it( 'silently ignores a malformed maximum rather than rejecting the value', () => {
		const property = newDateTimeProperty( { maximum: 'garbage' } );

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00Z' ), property ) ).toEqual( [] );
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
