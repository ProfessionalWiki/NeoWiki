import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';
import { SubjectId } from '@/domain/SubjectId';
import type { Constraint } from '@/domain/Constraint';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: string;
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
			uniqueItems: json.uniqueItems ?? true,
		} as RelationProperty;
	}

	public getConstraints( property: RelationProperty ): Constraint[] {
		return property.required ? [ { kind: 'required' } ] : [];
	}

	public validateValue( value: RelationValue | undefined ): ValueValidationError[] {
		if ( value === undefined || value.relations.length === 0 ) {
			return [];
		}
		const errors: ValueValidationError[] = [];
		for ( const relation of value.relations ) {
			if ( !SubjectId.isValid( relation.target.text ) ) {
				errors.push( { code: 'invalid-subject-id', args: [ relation.target.text ] } );
			}
		}
		return errors;
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
