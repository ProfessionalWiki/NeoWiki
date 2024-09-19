import { describe, expect, it } from 'vitest';
import { createPropertyDefinitionFromJson, PropertyName } from '@neo/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';

describe( 'PropertyDefinitionCollection', () => {

	const property1 = createPropertyDefinitionFromJson( 'test1', {
		type: 'number',
		format: 'number'
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
