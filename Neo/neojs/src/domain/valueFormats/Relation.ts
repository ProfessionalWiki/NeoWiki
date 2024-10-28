import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { Relation, RelationValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: string;
	readonly multiple?: boolean;

}

export class RelationFormat extends BaseValueFormat<RelationProperty, RelationValue> {

	public static readonly valueType = ValueType.Relation;

	public static readonly formatName = 'relation';

	public getExampleValue( property: RelationProperty ): RelationValue {
		const relations = [ new Relation( undefined, 's11111111111111' ) ];
		if ( property !== undefined && property.multiple ) {
			relations.push( new Relation( undefined, 's11111111111111' ) );
		}

		return new RelationValue( relations );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): RelationProperty {
		return {
			...base,
			relation: json.relation,
			targetSchema: json.targetSchema,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as RelationProperty;
	}

}

type RelationPropertyAttributes = Omit<Partial<RelationProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newRelationProperty( attributes: RelationPropertyAttributes = {} ): RelationProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Relation' ),
		format: RelationFormat.formatName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		relation: attributes.relation || 'MyRelation',
		targetSchema: attributes.targetSchema || 'MyTargetSchema',
		multiple: attributes.multiple ?? false
	};
}
