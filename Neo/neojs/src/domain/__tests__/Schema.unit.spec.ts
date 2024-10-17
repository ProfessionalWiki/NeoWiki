import { describe, expect, it } from 'vitest';
import { createPropertyDefinitionFromJson, PropertyName } from '@neo/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { ValueType } from '../Value';
import { newTextProperty, TextFormat } from '../valueFormats/Text';
import { newNumberProperty } from '../valueFormats/Number';
import { newSchema } from '@neo/TestHelpers';

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

} );
