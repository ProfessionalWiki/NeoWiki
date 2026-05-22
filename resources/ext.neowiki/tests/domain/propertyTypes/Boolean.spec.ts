import { describe, expect, it } from 'vitest';
import { BooleanType, newBooleanProperty } from '@/domain/propertyTypes/Boolean';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newBooleanValue } from '@/domain/Value';

describe( 'BooleanType', () => {
	const type = new BooleanType();

	it( 'returns "boolean" as the type name', () => {
		expect( type.getTypeName() ).toBe( 'boolean' );
	} );

	it( 'has no display attributes', () => {
		expect( type.getDisplayAttributeNames() ).toEqual( [] );
	} );

	it( 'provides a boolean example value', () => {
		expect( type.getExampleValue() ).toStrictEqual( newBooleanValue( true ) );
	} );
} );

describe( 'newBooleanProperty', () => {
	it( 'creates property with default values when no attributes provided', () => {
		const property = newBooleanProperty();

		expect( property.name ).toEqual( new PropertyName( 'Boolean' ) );
		expect( property.type ).toBe( BooleanType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
	} );

	it( 'creates property with custom name', () => {
		const property = newBooleanProperty( {
			name: 'Published',
		} );

		expect( property.name ).toEqual( new PropertyName( 'Published' ) );
	} );

	it( 'accepts a PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'Published' );
		const property = newBooleanProperty( {
			name: propertyName,
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all optional fields', () => {
		const property = newBooleanProperty( {
			name: 'Published',
			description: 'Whether the page is published',
			required: true,
			default: newBooleanValue( false ),
		} );

		expect( property.description ).toBe( 'Whether the page is published' );
		expect( property.required ).toBe( true );
		expect( property.default ).toStrictEqual( newBooleanValue( false ) );
	} );
} );

describe( 'validate', () => {
	const type = new BooleanType();

	it( 'returns no errors for a false value', () => {
		const errors = type.validate( newBooleanValue( false ), newBooleanProperty() );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns no errors for a true value', () => {
		const errors = type.validate( newBooleanValue( true ), newBooleanProperty() );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns no errors for an undefined value when optional', () => {
		const errors = type.validate( undefined, newBooleanProperty( { required: false } ) );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns a required error for an undefined value when required', () => {
		const errors = type.validate( undefined, newBooleanProperty( { required: true } ) );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors for a false value when required', () => {
		const errors = type.validate( newBooleanValue( false ), newBooleanProperty( { required: true } ) );

		expect( errors ).toEqual( [] );
	} );
} );
