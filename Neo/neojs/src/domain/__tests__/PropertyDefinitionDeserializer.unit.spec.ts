import { expect, it } from 'vitest';
import { PropertyDefinitionDeserializer } from '@neo/domain/PropertyDefinition';
import { newStringValue } from '../Value';
import { TextProperty, TextType } from '@neo/domain/propertyTypes/Text';
import { NumberProperty, NumberType } from '@neo/domain/propertyTypes/Number';
import { RelationProperty, RelationType } from '@neo/domain/propertyTypes/Relation';
import { Neo } from '@neo/Neo';

const serializer = new PropertyDefinitionDeserializer( Neo.getInstance().getValueFormatRegistry(), Neo.getInstance().getValueDeserializer() );

it( 'creates a property definition with defaults omitted', () => {
	const json = {
		format: 'number'
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json );

	expect( property.description ).toBe( '' );
	expect( property.required ).toBe( false );

	expect( property.name.toString() ).toBe( 'test' );
} );

it( 'creates a property definition with defaults specified', () => {
	const json = {
		format: 'number',
		description: 'Foo',
		required: true
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json );

	expect( property.description ).toBe( 'Foo' );
	expect( property.required ).toBe( true );
} );

it( 'creates a string property definition', () => {
	const json = {
		format: 'text',
		multiple: true,
		uniqueItems: false
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json ) as TextProperty;

	expect( property.type ).toBe( TextType.typeName );

	expect( property.multiple ).toBe( true );
	expect( property.uniqueItems ).toBe( false );
} );

it( 'creates a number property definition with defaults', () => {
	const json = {
		format: 'number'
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json ) as NumberProperty;

	expect( property.type ).toBe( NumberType.typeName );

	expect( property.minimum ).toBe( undefined );
	expect( property.maximum ).toBe( undefined );
	expect( property.precision ).toBe( undefined );
} );

it( 'creates a number property definition with all fields', () => {
	const json = {
		format: 'number',
		minimum: 42,
		maximum: 1337,
		precision: 2
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json ) as NumberProperty;

	expect( property.type ).toBe( NumberType.typeName );

	expect( property.minimum ).toBe( 42 );
	expect( property.maximum ).toBe( 1337 );
	expect( property.precision ).toBe( 2 );
} );

it( 'creates a relation property definition with defaults', () => {
	const json = {
		format: 'relation',
		relation: 'Employer',
		targetSchema: 'Company'
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json ) as RelationProperty;

	expect( property.type ).toBe( RelationType.typeName );

	expect( property.relation ).toBe( 'Employer' );
	expect( property.targetSchema ).toBe( 'Company' );
	expect( property.multiple ).toBe( false );
} );

it( 'creates a relation property definition with all fields', () => {
	const json = {
		format: 'relation',
		relation: 'Employer',
		targetSchema: 'Company',
		multiple: true,
		uniqueItems: false
	};

	const property = serializer.propertyDefinitionFromJson( 'test', json ) as RelationProperty;

	expect( property.multiple ).toBe( true );
} );

it( 'throws an error for an unsupported format', () => {
	const json = {
		format: 'unsupported'
	};

	expect( () => serializer.propertyDefinitionFromJson( 'test', json ) ).toThrow( 'Unknown property type: unsupported' );
} );

it( 'creates definitions without default value', () => {
	const property = serializer.propertyDefinitionFromJson(
		'test',
		{
			format: 'text'
		}
	);

	expect( property.default ).toBeUndefined();
} );

it( 'creates definitions with default value', () => {
	const property = serializer.propertyDefinitionFromJson(
		'test',
		{
			format: 'text',
			default: 'foo'
		}
	);

	expect( property.default ).toEqual( newStringValue( 'foo' ) );
} );

it( 'creates definitions with explicitly undefined default value', () => {
	const property = serializer.propertyDefinitionFromJson(
		'test',
		{
			format: 'text',
			default: undefined
		}
	);

	expect( property.default ).toBeUndefined();
} );
