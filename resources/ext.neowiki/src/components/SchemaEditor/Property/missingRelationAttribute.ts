import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { schemaReferenceName } from '@/domain/SchemaReference';
import { RelationType, type RelationProperty } from '@/domain/propertyTypes/Relation.ts';

/**
 * The message naming what a relation property definition still needs before the wiki will store
 * it, or null. Switching a property's type to Relation sets neither field, and the editor fills in
 * only the relation type.
 */
export function missingRelationAttribute( property: PropertyDefinition ): string | null {
	if ( property.type !== RelationType.typeName ) {
		return null;
	}

	const relation = property as RelationProperty;

	if ( !relation.relation ) {
		return 'neowiki-property-editor-relation-required';
	}

	if ( schemaReferenceName( relation.targetSchema ) === '' ) {
		return 'neowiki-property-editor-target-schema-required';
	}

	return null;
}
