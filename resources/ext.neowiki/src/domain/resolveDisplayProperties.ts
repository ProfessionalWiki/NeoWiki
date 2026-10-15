import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import type { Schema } from '@/domain/Schema';
import type { Subject } from '@/domain/Subject';
import type { SubjectId } from '@/domain/SubjectId';
import type { Layout } from '@/domain/Layout';
import { RelationValue, type Value } from '@/domain/Value';
import { isNamedAfterHostSubject } from '@/domain/dependentSubjects';

export interface ResolvedProperty {
	readonly propertyDefinition: PropertyDefinition;
	readonly value: Value;
}

/**
 * The properties a View shows: those the Display Rules of its Layout name, or without them every property with a
 * value, in Schema order, except the relation properties that point at Dependent Subjects.
 */
export function resolveDisplayProperties(
	schema: Schema,
	subject: Subject,
	layout: Layout | undefined,
	findSubject: ( id: SubjectId ) => Subject | undefined,
): ResolvedProperty[] {
	const displayRules = layout !== undefined && layout.getSchema() === subject.getSchemaName() ?
		layout.getDisplayRules() :
		[];

	if ( displayRules.length === 0 ) {
		return resolveAllNonEmptyProperties( schema, subject )
			.filter( ( resolved ) => !pointsAtDependentSubjects( resolved.value, findSubject ) );
	}

	return resolveByDisplayRules( schema, subject, displayRules );
}

/**
 * Every property with a value, in Schema order.
 */
export function resolveAllNonEmptyProperties( schema: Schema, subject: Subject ): ResolvedProperty[] {
	const result: ResolvedProperty[] = [];

	const nonEmptyNames = new Set( subject.getNamesOfNonEmptyProperties().map( ( n ) => n.toString() ) );

	for ( const propertyDefinition of schema.getPropertyDefinitions() ) {
		if ( !nonEmptyNames.has( propertyDefinition.name.toString() ) ) {
			continue;
		}

		const value = subject.getStatementValue( propertyDefinition.name );
		if ( value !== undefined ) {
			result.push( { propertyDefinition, value } );
		}
	}

	return result;
}

/**
 * Not by their Schema: a View reads no Schema for a Dependent Subject it does not show.
 */
function pointsAtDependentSubjects( value: Value, findSubject: ( id: SubjectId ) => Subject | undefined ): boolean {
	return value instanceof RelationValue && value.relations.some( ( relation ) => {
		const target = findSubject( relation.target );

		return target !== undefined && isNamedAfterHostSubject( target );
	} );
}

function resolveByDisplayRules(
	schema: Schema,
	subject: Subject,
	displayRules: ReturnType<Layout['getDisplayRules']>,
): ResolvedProperty[] {
	const result: ResolvedProperty[] = [];

	for ( const rule of displayRules ) {
		const propertyDefinition = getPropertyDefinition( schema, rule.property );
		if ( propertyDefinition === undefined ) {
			continue;
		}

		if ( !subject.getStatements().has( rule.property ) ) {
			continue;
		}

		const value = subject.getStatementValue( rule.property );
		if ( value === undefined ) {
			continue;
		}

		const mergedDefinition = rule.displayAttributes ?
			{ ...propertyDefinition, ...rule.displayAttributes } :
			propertyDefinition;

		result.push( { propertyDefinition: mergedDefinition, value } );
	}

	return result;
}

function getPropertyDefinition( schema: Schema, propertyName: PropertyName ): PropertyDefinition | undefined {
	return schema.getPropertyDefinitions().get( propertyName );
}
