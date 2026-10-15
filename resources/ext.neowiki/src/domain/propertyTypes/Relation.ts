import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';
import { isLocalSchemaReference, type SchemaReference } from '@/domain/SchemaReference';
import type { Schema } from '@/domain/Schema';

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

/**
 * The first Schema whose answer is known decides, since the target Schemas of one property all have Dependent
 * Subjects or none does. Null when no answer is known.
 */
export function targetsDependentSubjects(
	targetSchemaNames: readonly string[],
	subjectsAreDependent: ( schemaName: string ) => boolean | undefined,
): boolean | null {
	for ( const schemaName of targetSchemaNames ) {
		const answer = subjectsAreDependent( schemaName );

		if ( answer !== undefined ) {
			return answer;
		}
	}

	return null;
}

export function relationProperties( schema: Schema ): RelationProperty[] {
	return [ ...schema.getPropertyDefinitions() ]
		.filter( ( property ): property is RelationProperty => property.type === RelationType.typeName );
}

/**
 * Leaves out a property whose target Schemas `subjectsAreDependent` knows nothing about.
 */
export function propertiesTargetingDependentSubjects(
	schema: Schema,
	subjectsAreDependent: ( schemaName: string ) => boolean | undefined,
): RelationProperty[] {
	return relationProperties( schema ).filter(
		( property ) => targetsDependentSubjects( localTargetSchemaNames( property ), subjectsAreDependent ) === true,
	);
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
			targetSchemas: json.targetSchemas ?? singleTargetSchema( json ),
			multiple: json.multiple ?? false,
		} as RelationProperty;
	}

}

/**
 * Schemas saved before a relation property could list several target Schemas hold its one target in `targetSchema`.
 */
function singleTargetSchema( json: { targetSchema?: SchemaReference } ): SchemaReference[] {
	return json.targetSchema === undefined ? [] : [ json.targetSchema ];
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
