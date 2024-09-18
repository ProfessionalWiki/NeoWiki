import { describe, expect, it } from 'vitest';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition';
import { ValueType } from '../Value';
import type { TextProperty } from '../valueFormats/Text';
import { TextFormat } from '../valueFormats/Text';
import type { NumberProperty } from '../valueFormats/Number';
import { NumberFormat } from '../valueFormats/Number';
import type { RelationProperty } from '../valueFormats/Relation';
import { RelationFormat } from '../valueFormats/Relation';

describe( 'PropertyName constructor', () => {

	it( 'creates a valid PropertyName', () => {
		const id = new PropertyName( 'test' );
		expect( id.toString() ).toBe( 'test' );
	} );

	it( 'throws an error for an empty string', () => {
		expect( () => new PropertyName( '' ) ).toThrow( 'Invalid PropertyName' );
	} );

} );

describe( 'createPropertyDefinitionFromJson', () => {

	it( 'creates a property definition with defaults omitted', () => {
		const json = {
			type: 'number',
			format: 'number'
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );

		expect( property.name.toString() ).toBe( 'test' );
	} );

	it( 'creates a property definition with defaults specified', () => {
		const json = {
			type: 'number',
			format: 'number',
			description: 'Foo',
			required: true
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		expect( property.description ).toBe( 'Foo' );
		expect( property.required ).toBe( true );
	} );

	it( 'creates a string property definition', () => {
		const json = {
			type: 'string',
			format: 'text',
			multiple: true,
			uniqueItems: false
		};

		const property = createPropertyDefinitionFromJson( 'test', json ) as TextProperty;

		expect( property.type ).toBe( ValueType.String );
		expect( property.format ).toBe( TextFormat.formatName );

		expect( property.multiple ).toBe( true );
		expect( property.uniqueItems ).toBe( false );
	} );

	it( 'creates a number property definition with defaults', () => {
		const json = {
			type: 'number',
			format: 'number'
		};

		const property = createPropertyDefinitionFromJson( 'test', json ) as NumberProperty;

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( NumberFormat.formatName );

		expect( property.minimum ).toBe( undefined );
		expect( property.maximum ).toBe( undefined );
		expect( property.precision ).toBe( undefined );
	} );

	it( 'creates a number property definition with all fields', () => {
		const json = {
			type: 'number',
			format: 'number',
			minimum: 42,
			maximum: 1337,
			precision: 2
		};

		const property = createPropertyDefinitionFromJson( 'test', json ) as NumberProperty;

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( NumberFormat.formatName );

		expect( property.minimum ).toBe( 42 );
		expect( property.maximum ).toBe( 1337 );
		expect( property.precision ).toBe( 2 );
	} );

	it( 'creates a relation property definition with defaults', () => {
		const json = {
			type: 'relation',
			format: 'relation',
			relation: 'Employer',
			targetSchema: 'Company'
		};

		const property = createPropertyDefinitionFromJson( 'test', json ) as RelationProperty;

		expect( property.type ).toBe( ValueType.Relation );
		expect( property.format ).toBe( RelationFormat.formatName );

		expect( property.relation ).toBe( 'Employer' );
		expect( property.targetSchema ).toBe( 'Company' );
		expect( property.multiple ).toBe( false );
	} );

	it( 'creates a relation property definition with all fields', () => {
		const json = {
			type: 'relation',
			format: 'relation',
			relation: 'Employer',
			targetSchema: 'Company',
			multiple: true,
			uniqueItems: false
		};

		const property = createPropertyDefinitionFromJson( 'test', json ) as RelationProperty;

		expect( property.type ).toBe( ValueType.Relation );

		expect( property.multiple ).toBe( true );
	} );

	it( 'throws an error for an unsupported format', () => {
		const json = {
			type: 'string',
			format: 'unsupported'
		};

		expect( () => createPropertyDefinitionFromJson( 'test', json ) ).toThrow( 'Unknown value format: unsupported' );
	} );

	it( 'creates definitions without default value', () => {
		const property = createPropertyDefinitionFromJson(
			'test',
			{
				type: 'string',
				format: 'text'
			}
		);

		expect( property.default ).toBeUndefined();
	} );

	it( 'creates definitions with default value', () => {
		const property = createPropertyDefinitionFromJson(
			'test',
			{
				type: 'string',
				format: 'text',
				default: 'foo'
			}
		);

		expect( property.default ).toBe( 'foo' );
	} );

} );
