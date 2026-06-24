import { describe, expect, it } from 'vitest';
import { newNumberProperty, NumberType, type NumberProperty } from '@/domain/propertyTypes/Number';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue } from '@/domain/Value';

function numberPropertyFromJson( json: Record<string, unknown> ): NumberProperty {
	return new NumberType().createPropertyDefinitionFromJson(
		{ name: new PropertyName( 'Year' ), type: 'number', description: '', required: false },
		{ type: 'number', ...json },
	);
}

describe( 'NumberType', () => {

	it( 'returns precision as display attribute', () => {
		expect( new NumberType().getDisplayAttributeNames() ).toEqual( [ 'precision' ] );
	} );

} );

describe( 'newNumberProperty', () => {
	it( 'creates property with default values when no options provided', () => {
		const property = newNumberProperty();

		expect( property.name ).toEqual( new PropertyName( 'Number' ) );
		expect( property.type ).toBe( NumberType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.precision ).toBeUndefined();
		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );

	it( 'creates property with custom name', () => {
		const property = newNumberProperty( {
			name: 'CustomNumber',
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomNumber' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customNumber' );
		const property = newNumberProperty( {
			name: propertyName,
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all optional fields', () => {
		const property = newNumberProperty( {
			name: 'FullNumber',
			description: 'A number property',
			required: true,
			default: newNumberValue( 42 ),
			precision: 2,
			minimum: 0,
			maximum: 100,
		} );

		expect( property.name ).toEqual( new PropertyName( 'FullNumber' ) );
		expect( property.type ).toBe( NumberType.typeName );
		expect( property.description ).toBe( 'A number property' );
		expect( property.required ).toBe( true );
		expect( property.default ).toStrictEqual( newNumberValue( 42 ) );
		expect( property.precision ).toBe( 2 );
		expect( property.minimum ).toBe( 0 );
		expect( property.maximum ).toBe( 100 );
	} );

	it( 'creates property with some optional fields', () => {
		const property = newNumberProperty( {
			name: 'PartialNumber',
			description: 'A partial number property',
			precision: 2,
		} );

		expect( property.name ).toEqual( new PropertyName( 'PartialNumber' ) );
		expect( property.type ).toBe( NumberType.typeName );
		expect( property.description ).toBe( 'A partial number property' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.precision ).toBe( 2 );
		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );
} );

describe( 'createPropertyDefinitionFromJson', () => {

	it( 'normalizes null precision, minimum and maximum to undefined', () => {
		// The PHP serializer emits these as null when unset; null must not leak into the number|undefined fields.
		const property = numberPropertyFromJson( { precision: null, minimum: null, maximum: null } );

		expect( property.precision ).toBeUndefined();
		expect( property.minimum ).toBeUndefined();
		expect( property.maximum ).toBeUndefined();
	} );

	it( 'preserves zero precision, minimum and maximum as real values', () => {
		const property = numberPropertyFromJson( { precision: 0, minimum: 0, maximum: 0 } );

		expect( property.precision ).toBe( 0 );
		expect( property.minimum ).toBe( 0 );
		expect( property.maximum ).toBe( 0 );
	} );
} );

describe( 'validate', () => {
	const numberType = new NumberType();

	it( 'returns no errors for undefined value when optional', () => {
		const property = newNumberProperty( {
			required: false,
		} );

		const errors = numberType.validate( undefined, property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newNumberProperty( {
			required: true,
		} );

		const errors = numberType.validate( undefined, property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors for valid number within bounds', () => {
		const property = newNumberProperty( {
			minimum: 0,
			maximum: 100,
		} );

		const errors = numberType.validate( newNumberValue( 50 ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns no errors for number equal to bounds', () => {
		const property = newNumberProperty( {
			minimum: 42,
			maximum: 42,
		} );

		const errors = numberType.validate( newNumberValue( 42 ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns min-value error when below minimum', () => {
		const property = newNumberProperty( {
			minimum: 0,
		} );

		const errors = numberType.validate( newNumberValue( -1 ), property );

		expect( errors ).toEqual( [ {
			code: 'min-value',
			args: [ 0 ],
		} ] );
	} );

	it( 'returns max-value error when above maximum', () => {
		const property = newNumberProperty( {
			maximum: 100,
		} );

		const errors = numberType.validate( newNumberValue( 101 ), property );

		expect( errors ).toEqual( [ {
			code: 'max-value',
			args: [ 100 ],
		} ] );
	} );

	it( 'treats a null maximum from backend serialization as unset', () => {
		// Regression: a null maximum coerced to 0, so any positive value reported "Maximum value is null".
		const property = numberPropertyFromJson( { minimum: null, maximum: null } );

		const errors = numberType.validate( newNumberValue( 2025 ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'treats a null minimum from backend serialization as unset', () => {
		// Regression: a null minimum coerced to 0, so any negative value reported "Minimum value is null".
		const property = numberPropertyFromJson( { minimum: null, maximum: null } );

		const errors = numberType.validate( newNumberValue( -5 ), property );

		expect( errors ).toEqual( [] );
	} );
} );
