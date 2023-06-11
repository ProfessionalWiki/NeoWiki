import {
	createPropertyDefinitionFromJson,
	ValueFormat,
	ValueType,
	PropertyName,
	PropertyDefinitionList
} from '@/editor/domain/Schema';
import { describe, expect, it } from 'vitest';

describe( 'PropertyId constructor', () => {

	it( 'creates a valid PropertyId', () => {
		const id = new PropertyName( 'test' );
		expect( id.toString() ).toBe( 'test' );
	} );

	it( 'throws an error for an empty string', () => {
		expect( () => new PropertyName( '' ) ).toThrow( 'Invalid PropertyId' );
	} );

} );

describe( 'createPropertyDefinitionFromJson', () => {

	it( 'creates a property definition with defaults omitted', () => {
		const json = {
			type: 'boolean',
			format: 'checkbox'
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );

		expect( property.name.toString() ).toBe( 'test' );
	} );

	it( 'creates a property definition with defaults specified', () => {
		const json = {
			type: 'boolean',
			format: 'checkbox',
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

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.type === ValueType.String ) {
			expect( property.multiple ).toBe( true );
			expect( property.uniqueItems ).toBe( false );
		}

		expect( property.type ).toBe( ValueType.String );
		expect( property.format ).toBe( ValueFormat.Text );
	} );

	it( 'creates a number property definition with defaults', () => {
		const json = {
			type: 'number',
			format: 'number'
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.format === ValueFormat.Number ) {
			expect( property.minimum ).toBe( undefined );
			expect( property.maximum ).toBe( undefined );
			expect( property.precision ).toBe( undefined );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Number );
	} );

	it( 'creates a number property definition with all fields', () => {
		const json = {
			type: 'number',
			format: 'number',
			minimum: 42,
			maximum: 1337,
			precision: 2
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.format === ValueFormat.Number ) {
			expect( property.minimum ).toBe( 42 );
			expect( property.maximum ).toBe( 1337 );
			expect( property.precision ).toBe( 2 );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Number );
	} );

	it( 'creates a currency property definition with defaults', () => {
		const json = {
			type: 'number',
			format: 'currency',
			currencyCode: 'EUR',
			precision: 2
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.format === ValueFormat.Currency ) {
			expect( property.currencyCode ).toBe( 'EUR' );
			expect( property.precision ).toBe( 2 );
			expect( property.minimum ).toBe( undefined );
			expect( property.maximum ).toBe( undefined );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Currency );
	} );

	it( 'creates a currency property definition with all fields', () => {
		const json = {
			type: 'number',
			format: 'currency',
			currencyCode: 'EUR',
			precision: 2,
			minimum: 42,
			maximum: 1337
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.format === ValueFormat.Currency ) {
			expect( property.currencyCode ).toBe( 'EUR' );
			expect( property.precision ).toBe( 2 );
			expect( property.minimum ).toBe( 42 );
			expect( property.maximum ).toBe( 1337 );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Currency );
	} );

	it( 'creates a currency progress property definition', () => {
		const json = {
			type: 'number',
			format: 'progress',
			minimum: 42,
			maximum: 1337,
			step: 23
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.format === ValueFormat.Progress ) {
			expect( property.minimum ).toBe( 42 );
			expect( property.maximum ).toBe( 1337 );
			expect( property.step ).toBe( 23 );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Progress );
	} );

	it( 'creates a boolean property definition', () => {
		const json = {
			type: 'boolean',
			format: 'checkbox'
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		expect( property.type ).toBe( ValueType.Boolean );
		expect( property.format ).toBe( ValueFormat.Checkbox );
	} );

	it( 'creates a relation property definition with defaults', () => {
		const json = {
			type: 'relation',
			format: 'relation',
			relation: 'Employer',
			targetSchema: 'Company'
		};

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.type === ValueType.Relation ) {
			expect( property.relation ).toBe( 'Employer' );
			expect( property.targetSchema ).toBe( 'Company' );
			expect( property.multiple ).toBe( false );
			expect( property.uniqueItems ).toBe( true );
		}

		expect( property.type ).toBe( ValueType.Relation );
		expect( property.format ).toBe( ValueFormat.Relation );
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

		const property = createPropertyDefinitionFromJson( 'test', json );

		if ( property.type === ValueType.Relation ) {
			expect( property.multiple ).toBe( true );
			expect( property.uniqueItems ).toBe( false );
		}

		expect( property.type ).toBe( ValueType.Relation );
	} );

	it( 'throws an error for an unsupported type', () => {
		const json = {
			type: 'unsupported',
			format: 'text'
		};

		expect( () => createPropertyDefinitionFromJson( 'test', json ) ).toThrow( 'Unsupported type: unsupported' );
	} );

} );

describe( 'PropertyDefinitionCollection', () => {

	const property1 = createPropertyDefinitionFromJson( 'test1', {
		type: 'boolean',
		format: 'checkbox'
	} );

	const property2 = createPropertyDefinitionFromJson( 'test2', {
		type: 'string',
		format: 'text'
	} );

	it( 'constructs a collection from an array of property definitions', () => {
		const collection = new PropertyDefinitionList( [ property1, property2 ] );

		expect( collection.get( new PropertyName( 'test1' ) ) ).toEqual( property1 );
		expect( collection.get( new PropertyName( 'test2' ) ) ).toEqual( property2 );
	} );

	it( 'throws an error when constructing a collection with duplicate property ids', () => {
		expect( () => new PropertyDefinitionList( [
			property1,
			property1
		] ) ).toThrow( 'Duplicate property name: test1' );
	} );

	it( 'allows iteration over the properties in the collection', () => {
		const collection = new PropertyDefinitionList( [ property1, property2 ] );
		const properties = [];

		for ( const property of collection ) {
			properties.push( property );
		}

		expect( properties ).toEqual( [ property1, property2 ] );
	} );

	it( 'has only elements it actually does have', () => {
		const collection = new PropertyDefinitionList( [ property1, property2 ] );

		expect( collection.has( new PropertyName( 'test2' ) ) ).toBe( true );
		expect( collection.has( new PropertyName( 'test3' ) ) ).toBe( false );
	} );

	describe( 'withNames', () => {

		const collection = new PropertyDefinitionList( [ property1, property2 ] );

		it( 'creates a new collection from a list of property names', () => {
			const newCollection = collection.withNames( [ new PropertyName( 'test1' ) ] );

			expect( newCollection.get( new PropertyName( 'test1' ) ) ).toEqual( property1 );
			expect( newCollection.has( new PropertyName( 'test2' ) ) ).toBe( false );
		} );

		it( 'ignores unknown property names when creating a new collection', () => {
			const newCollection = collection.withNames( [ new PropertyName( 'test1' ), new PropertyName( 'test3' ) ] );

			expect( newCollection.get( new PropertyName( 'test1' ) ) ).toEqual( property1 );
			expect( newCollection.has( new PropertyName( 'test2' ) ) ).toBe( false );
			expect( newCollection.has( new PropertyName( 'test3' ) ) ).toBe( false );
		} );

		it( 'returns a new collection with the same order as the input names', () => {
			expect(
				collection.withNames( [
					new PropertyName( 'test2' ),
					new PropertyName( 'test1' ),
					new PropertyName( 'test2' )
				] )
			).toEqual( new PropertyDefinitionList( [ property1, property2 ] ) );
		} );

	} );

} );
