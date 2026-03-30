import { describe, expect, it } from 'vitest';
import { newDateTimeProperty, DateTimeType } from '@/domain/propertyTypes/DateTime';
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

	it( 'returns min-value error when before minimum', () => {
		const property = newDateTimeProperty( { minimum: '2025-01-01T00:00:00Z' } );

		expect( dateTimeType.validate( newStringValue( '2024-12-31T23:59:59Z' ), property ) ).toEqual( [
			{ code: 'min-value', args: [ '2025-01-01T00:00:00Z' ] },
		] );
	} );

	it( 'returns max-value error when after maximum', () => {
		const property = newDateTimeProperty( { maximum: '2025-12-31T23:59:59Z' } );

		expect( dateTimeType.validate( newStringValue( '2026-01-01T00:00:00Z' ), property ) ).toEqual( [
			{ code: 'max-value', args: [ '2025-12-31T23:59:59Z' ] },
		] );
	} );

	it( 'returns no errors for datetime equal to bounds', () => {
		const property = newDateTimeProperty( {
			minimum: '2025-06-15T12:00:00Z',
			maximum: '2025-06-15T12:00:00Z',
		} );

		expect( dateTimeType.validate( newStringValue( '2025-06-15T12:00:00Z' ), property ) ).toEqual( [] );
	} );

} );
