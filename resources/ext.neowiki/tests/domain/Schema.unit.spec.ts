import { describe, expect, it } from 'vitest';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newSchema } from '@/TestHelpers';
import { newNumberProperty } from '@/domain/propertyTypes/Number';

describe( 'Schema', () => {

	describe( 'getPropertyDefinition', () => {

		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Wrong' } ),
				newTextProperty( { name: 'MyText' } ),
				newTextProperty( { name: 'AlsoWrong' } ),
			] ),
		} );

		it( 'returns undefined for unknown property', () => {
			expect( schema.getPropertyDefinition( '404' ) ).toBeUndefined();
		} );

		it( 'returns known property definition', () => {
			const property = schema.getPropertyDefinition( 'MyText' );

			expect( property ).toStrictEqual( newTextProperty( { name: 'MyText' } ) );
		} );

	} );

	describe( 'withName', () => {

		it( 'returns a new Schema with the updated name', () => {
			const originalSchema = newSchema();

			const updatedSchema = originalSchema.withName( 'Updated Schema' );

			expect( updatedSchema.getName() ).toBe( 'Updated Schema' );
			expect( updatedSchema.getDescription() ).toBe( originalSchema.getDescription() );
			expect( updatedSchema.getPropertyDefinitions() ).toEqual( originalSchema.getPropertyDefinitions() );
			expect( updatedSchema ).not.toBe( originalSchema );
		} );

	} );

	describe( 'withAddedPropertyDefinition', () => {

		it( 'adds a Property Definition when Schema has no properties', () => {
			const originalSchema = newSchema();

			const addedProperty = newTextProperty();

			const updatedSchema = originalSchema.withAddedPropertyDefinition( addedProperty );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ addedProperty.name.toString() ]: addedProperty,
			} );
		} );

		it( 'adds a Property Definition when Schema has properties', () => {
			const existingProperty = newTextProperty();

			const originalSchema = newSchema( {
				properties: new PropertyDefinitionList( [ existingProperty ] ),
			} );

			const addedProperty = newNumberProperty();

			const updatedSchema = originalSchema.withAddedPropertyDefinition( addedProperty );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ existingProperty.name.toString() ]: existingProperty,
				[ addedProperty.name.toString() ]: addedProperty,
			} );
		} );

	} );

	describe( 'withRemovedPropertyDefinition', () => {

		it( 'removes a Property Definition', () => {
			const property1 = newTextProperty();
			const property2 = newNumberProperty();

			const originalSchema = newSchema( {
				properties: new PropertyDefinitionList( [ property1, property2 ] ),
			} );

			const updatedSchema = originalSchema.withRemovedPropertyDefinition( property1.name );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ property2.name.toString() ]: property2,
			} );
		} );

	} );

} );
