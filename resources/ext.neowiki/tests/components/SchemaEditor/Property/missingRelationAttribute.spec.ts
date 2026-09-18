import { describe, expect, it } from 'vitest';
import { missingRelationAttribute } from '@/components/SchemaEditor/Property/missingRelationAttribute.ts';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';

describe( 'missingRelationAttribute', () => {
	function relationProperty( overrides: Partial<RelationProperty> = {} ): RelationProperty {
		return { ...newRelationProperty(), relation: 'Has product', targetSchema: 'Product', ...overrides };
	}

	it( 'finds nothing missing from a complete definition', () => {
		expect( missingRelationAttribute( relationProperty() ) ).toBeNull();
	} );

	it( 'finds nothing missing from a property of another type', () => {
		expect( missingRelationAttribute( newNumberProperty( { name: 'Score' } ) ) ).toBeNull();
	} );

	it( 'names the target schema when it was never chosen', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: undefined } ) ) )
			.toBe( 'neowiki-property-editor-target-schema-required' );
	} );

	it( 'accepts a target schema from another Source', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: { source: 'otherwiki', name: 'Person' } } ) ) )
			.toBeNull();
	} );
} );
