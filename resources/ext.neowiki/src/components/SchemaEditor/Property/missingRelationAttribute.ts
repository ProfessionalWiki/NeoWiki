import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { RelationType, type RelationProperty } from '@/domain/propertyTypes/Relation.ts';

/**
 * The message naming what a relation property definition still needs before the wiki will store
 * it, or null. Switching a property's type to Relation leaves it without target schemas, which
 * only the user can supply.
 */
export function missingRelationAttribute( property: PropertyDefinition ): string | null {
	if ( property.type !== RelationType.typeName ) {
		return null;
	}

	if ( ( property as RelationProperty ).targetSchemas.length === 0 ) {
		return 'neowiki-property-editor-target-schema-required';
	}

	return null;
}
