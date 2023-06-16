import { describe, expect, it } from 'vitest';
import { createPropertyDefinitionFromJson, ValueFormat, ValueType } from '@/editor/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/editor/domain/PropertyDefinitionList';
import { newSchema } from '../../../TestHelpers';

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
			expect( property.format ).toBe( ValueFormat.Text );
		} );

	} );

} );
