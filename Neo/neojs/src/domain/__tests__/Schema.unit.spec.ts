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

			expect( property.type ).toBe( ValueType.String );
			expect( property.format ).toBe( TextFormat.formatName );
		} );

	} );

	describe( 'getTypeOf', () => {

		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( 'One' ),
				newNumberProperty( 'Two' ),
				newTextProperty( 'Three' )
			] )
		} );

		it( 'returns type of present property', () => {
			expect( schema.getTypeOf( new PropertyName( 'Two' ) ) ).toBe( ValueType.Number );
		} );

		it( 'returns undefined for unknown property', () => {
			expect( schema.getTypeOf( new PropertyName( 'Four' ) ) ).toBeUndefined();
		} );

	} );

} );
