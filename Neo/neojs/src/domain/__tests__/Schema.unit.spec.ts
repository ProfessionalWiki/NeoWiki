import { describe, expect, it } from 'vitest';
import { createPropertyDefinitionFromJson } from '@neo/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { newTextProperty, TextFormat } from '@neo/domain/valueFormats/Text';
import { newSchema } from '@neo/TestHelpers';
import { newNumberProperty } from '@neo/domain/valueFormats/Number';

describe( 'Schema', () => {

	describe( 'getPropertyDefinition', () => {

		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'test', {
					type: 'string',
					format: 'text'
				} )
			] )
		} );

		it( 'returns undefined for unknown property', () => {
			expect( schema.getPropertyDefinition( '404' ) ).toBeUndefined();
		} );

		it( 'returns known property definition', () => {
			const property = schema.getPropertyDefinition( 'test' );

			expect( property.format ).toBe( TextFormat.formatName );
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

			const addedProperty = createPropertyDefinitionFromJson( 'addedProperty', {
				type: 'string',
				format: 'text'
			} );

			const updatedSchema = originalSchema.withAddedPropertyDefinition( addedProperty );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				addedProperty: addedProperty
			} );
		} );

		it( 'adds a Property Definition when Schema has properties', () => {
			const existingProperty = createPropertyDefinitionFromJson( 'existingProperty', {
				type: 'string',
				format: 'text'
			} );

			const originalSchema = newSchema( {
				properties: new PropertyDefinitionList( [ existingProperty ] )
			} );

			const addedProperty = createPropertyDefinitionFromJson( 'addedProperty', {
				type: 'number',
				format: 'number'
			} );

			const updatedSchema = originalSchema.withAddedPropertyDefinition( addedProperty );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				existingProperty: existingProperty,
				addedProperty: addedProperty
			} );
		} );

	} );

	describe( 'withRemovedPropertyDefinition', () => {

		it( 'removes a Property Definition', () => {
			const property1 = newTextProperty();
			const property2 = newNumberProperty();

			const originalSchema = newSchema( {
				properties: new PropertyDefinitionList( [ property1, property2 ] )
			} );

			const updatedSchema = originalSchema.withRemovedPropertyDefinition( property1.name );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ property2.name.toString() ]: property2
			} );
		} );

	} );

} );
