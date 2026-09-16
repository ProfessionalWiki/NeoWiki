import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';
import { schemaReferenceName, type SchemaReference } from '@/domain/SchemaReference';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: SchemaReference;
	readonly multiple?: boolean;

}

export class RelationType extends BasePropertyType<RelationProperty, RelationValue> {

	public static readonly valueType = ValueType.Relation;

	public static readonly typeName = 'relation';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue( property: RelationProperty ): RelationValue {
		const relations = [ newRelation( undefined, 's11111111111111' ) ];
		if ( property !== undefined && property.multiple ) {
			relations.push( newRelation( undefined, 's11111111111111' ) );
		}

		return new RelationValue( relations );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): RelationProperty {
		return {
			...base,
			relation: json.relation,
			targetSchema: json.targetSchema,
			multiple: json.multiple ?? false,
		} as RelationProperty;
	}

}

/**
 * The message naming what the definition still needs before the wiki will store it, or null.
 * Both fields are required by the Schema content format, and switching a property to this type
 * supplies neither, so an editor that does not ask for them saves something the server refuses.
 */
export function missingRelationAttribute( property: RelationProperty ): string | null {
	if ( ( property.relation ?? '' ).trim() === '' ) {
		return 'neowiki-property-editor-relation-required';
	}

	if ( schemaReferenceName( property.targetSchema ).trim() === '' ) {
		return 'neowiki-property-editor-target-schema-required';
	}

	return null;
}

type RelationPropertyAttributes = Omit<Partial<RelationProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newRelationProperty( attributes: RelationPropertyAttributes = {} ): RelationProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Relation' ),
		type: RelationType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		relation: attributes.relation || 'MyRelation',
		targetSchema: attributes.targetSchema || 'MyTargetSchema',
		multiple: attributes.multiple ?? false,
	};
}
