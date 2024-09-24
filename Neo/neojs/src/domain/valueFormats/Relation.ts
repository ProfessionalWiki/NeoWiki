import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { Relation, RelationValue, ValueType } from '@neo/domain/Value';
import { BaseValueFormat } from '@neo/domain/ValueFormat';
import { Uuid } from '@neo/infrastructure/Uuid';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: string;
	readonly multiple?: boolean;

}

export class RelationFormat extends BaseValueFormat<RelationProperty, RelationValue> {

	public static readonly valueType = ValueType.String;

	public static readonly formatName = 'relation';

	public getExampleValue( property: RelationProperty ): RelationValue {
		const relations = [ new Relation( undefined, Uuid.getRandomUUID() ) ];
		if ( property !== undefined && property.multiple ) {
			relations.push( new Relation( undefined, Uuid.getRandomUUID() ) );
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

	public getInfoboxValueComponentName(): string {
		return 'RelationValue';
	}

}

export function newRelationProperty( name: string, targetSchema?: string, multiple?: boolean ): RelationProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Relation,
		format: RelationFormat.formatName,
		description: '',
		required: false,
		default: '',
		relation: 'MyRelation',
		targetSchema: targetSchema ?? 'MyTargetSchema',
		multiple: multiple ?? false
	};
}
