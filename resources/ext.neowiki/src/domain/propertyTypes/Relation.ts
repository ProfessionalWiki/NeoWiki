import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';
import type { SchemaReference } from '@/domain/SchemaReference';

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

	// The relation type is the edge label of the native projections, which the wiki requires and the
	// editor does not show. A Schema loaded from the wiki always carries a non-empty one, so the
	// property name only stands in where there is none: for a property just switched to this type.
	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): RelationProperty {
		return {
			...base,
			relation: json.relation || base.name.toString(),
			targetSchema: json.targetSchema,
			multiple: json.multiple ?? false,
		} as RelationProperty;
	}

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
