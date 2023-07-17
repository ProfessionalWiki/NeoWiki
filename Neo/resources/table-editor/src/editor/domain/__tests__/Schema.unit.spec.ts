import { describe, expect, it } from 'vitest';
import { createPropertyDefinitionFromJson, Format, PropertyName } from '@/editor/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/editor/domain/PropertyDefinitionList';
import { newSchema } from '../../../TestHelpers';
import { ValueType } from '../Value';
import { newTextProperty } from '../valueFormats/Text';
import { newNumberProperty } from '../valueFormats/Number';

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
			expect( property.format ).toBe( Format.Text );
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
