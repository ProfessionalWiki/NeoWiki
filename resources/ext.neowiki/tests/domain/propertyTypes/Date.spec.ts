import { describe, expect, it } from 'vitest';
import {
	newDateProperty,
	DateType,
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

describe( 'createPropertyDefinitionFromJson', () => {
	const dateType = new DateType();

	it( 'normalizes null minimum and maximum to undefined', () => {
		// The PHP serializer emits these as null when unset; null must not leak into the string|undefined fields.
		const property = dateType.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'Date' ), type: 'date', description: '', required: false },
			{ type: 'date', minimum: null, maximum: null },
		);

		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );

	it( 'keeps minPrecision, so saving the Schema does not drop it', () => {
		const property = dateType.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'Date' ), type: 'date', description: '', required: false },
			{ type: 'date', minPrecision: 'month' },
		);

		expect( property.minPrecision ).toBe( 'month' );
	} );

	it( 'drops a bound that is not a string, which the backend cannot read either', () => {
		const property = dateType.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'Date' ), type: 'date', description: '', required: false },
			{ type: 'date', minimum: 1984, maximum: '1990' },
		);

		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBe( '1990' );
	} );

	it( 'normalizes null minPrecision to undefined', () => {
		const property = dateType.createPropertyDefinitionFromJson(
			{ name: new PropertyName( 'Date' ), type: 'date', description: '', required: false },
			{ type: 'date', minPrecision: null },
		);

		expect( property.minPrecision ).toBeUndefined();
	} );
} );
