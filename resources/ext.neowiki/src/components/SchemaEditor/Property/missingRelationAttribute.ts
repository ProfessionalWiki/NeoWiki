import type { SchemaReference } from '@/domain/SchemaReference';
import type { RelationProperty } from '@/domain/propertyTypes/Relation.ts';

/**
 * The message naming what the definition still needs before the wiki will store it, or null.
 * Both fields are required by the Schema content format, and switching a property to this type
 * supplies neither, so an editor that does not ask for them saves something the server refuses.
 *
 * Lives beside the editor that asks for them rather than with the Property Type: it answers in
 * messages, which the domain does not deal in. Letting each Property Type answer for its own
 * definition, so an extension's can too, is #1454.
 */
export function missingRelationAttribute( property: RelationProperty ): string | null {
	if ( typeof property.relation !== 'string' || property.relation.trim() === '' ) {
		return 'neowiki-property-editor-relation-required';
	}

	if ( targetSchemaName( property.targetSchema ).trim() === '' ) {
		return 'neowiki-property-editor-target-schema-required';
	}

	return null;
}

/**
 * Anything the wiki's content validation would have refused counts as absent rather than throwing:
 * a Schema that never passed it can still be opened here (an XML import bypasses it), and this runs
 * over every relation property whenever a Schema is saved.
 */
function targetSchemaName( target: SchemaReference | undefined ): string {
	if ( typeof target === 'string' ) {
		return target;
	}

	if ( target === null || typeof target !== 'object' || typeof target.name !== 'string' ) {
		return '';
	}

	return target.name;
}
