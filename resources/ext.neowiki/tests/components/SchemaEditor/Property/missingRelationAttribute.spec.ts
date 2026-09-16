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

	// Switching a property's type to Relation sets neither field. The relation type comes
	// first because it is the one the editor fills in for the user.
	it( 'names the relation type first when neither field is set', () => {
		const neither: Partial<RelationProperty> = { relation: undefined, targetSchema: undefined };

		expect( missingRelationAttribute( relationProperty( neither ) ) )
			.toBe( 'neowiki-property-editor-relation-required' );
	} );

	it( 'names the target schema when it was never chosen', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: undefined } ) ) )
			.toBe( 'neowiki-property-editor-target-schema-required' );
	} );

	it( 'names the relation type when it was cleared', () => {
		expect( missingRelationAttribute( relationProperty( { relation: '' } ) ) )
			.toBe( 'neowiki-property-editor-relation-required' );
	} );

	it( 'accepts a target schema from another Source', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: { source: 'otherwiki', name: 'Person' } } ) ) )
			.toBeNull();
	} );
} );
