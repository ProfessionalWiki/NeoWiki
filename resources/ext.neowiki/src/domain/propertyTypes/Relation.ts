import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';
import { isLocalSchemaReference, type SchemaReference } from '@/domain/SchemaReference';

export interface RelationProperty extends PropertyDefinition {

	/**
	 * Empty for a property that has none, such as one whose type was just switched to Relation, which
	 * the wiki refuses to save.
	 */
	readonly targetSchemas: readonly SchemaReference[];
	readonly multiple?: boolean;

}

/**
 * The property's target Schemas of this wiki, the only ones a Subject created here can have. A property
 * given another type since a Statement was written has none.
 */
export function localTargetSchemaNames( property: RelationProperty ): string[] {
	return ( property.targetSchemas ?? [] ).filter( isLocalSchemaReference );
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
			targetSchemas: json.targetSchemas ?? [],
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
		targetSchemas: attributes.targetSchemas ?? [ 'MyTargetSchema' ],
		multiple: attributes.multiple ?? false,
	};
}
