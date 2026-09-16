import { describe, expect, it } from 'vitest';
import { missingRelationAttribute } from '@/components/SchemaEditor/Property/missingRelationAttribute.ts';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';

describe( 'missingRelationAttribute', () => {
	function relationProperty( overrides: Partial<RelationProperty> = {} ): RelationProperty {
		return { ...newRelationProperty(), relation: 'Has product', targetSchema: 'Product', ...overrides };
	}

	it( 'finds nothing missing from a complete definition', () => {
		expect( missingRelationAttribute( relationProperty() ) ).toBeNull();
	} );

	// What switching a property's type to Relation actually leaves behind: neither field set.
	// The relation type is named first because it is the field the editor fills in for you.
	it( 'names the relation type first when neither field is set', () => {
		const neither: Partial<RelationProperty> = { relation: undefined, targetSchema: undefined };

		expect( missingRelationAttribute( relationProperty( neither ) ) )
			.toBe( 'neowiki-property-editor-relation-required' );
	} );

	it( 'names the target schema when it was never chosen', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: undefined } ) ) )
			.toBe( 'neowiki-property-editor-target-schema-required' );
	} );

	it( 'names the target schema when it is blank', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: '   ' } ) ) )
			.toBe( 'neowiki-property-editor-target-schema-required' );
	} );

	it( 'names the relation type when it is blank', () => {
		expect( missingRelationAttribute( relationProperty( { relation: '  ' } ) ) )
			.toBe( 'neowiki-property-editor-relation-required' );
	} );

	// A Schema that never passed the wiki's content validation can still be opened here, and this
	// runs over every relation property on a save, so a throw would abort the save silently.
	it.each( [
		[ 'a null target schema', { targetSchema: null } ],
		[ 'a target schema that is neither a name nor a reference', { targetSchema: 42 } ],
		[ 'a target schema object without a name', { targetSchema: { source: 'otherwiki' } } ],
	] )( 'treats %s as missing rather than throwing', ( _label, overrides ) => {
		expect( missingRelationAttribute( relationProperty( overrides as Partial<RelationProperty> ) ) )
			.toBe( 'neowiki-property-editor-target-schema-required' );
	} );

	it( 'treats a relation type that is not text as missing rather than throwing', () => {
		expect( missingRelationAttribute( relationProperty( { relation: 42 } as unknown as Partial<RelationProperty> ) ) )
			.toBe( 'neowiki-property-editor-relation-required' );
	} );

	it( 'accepts a target schema from another Source', () => {
		expect( missingRelationAttribute( relationProperty( { targetSchema: { source: 'otherwiki', name: 'Person' } } ) ) )
			.toBeNull();
	} );
} );
