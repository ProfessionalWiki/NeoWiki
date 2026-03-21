import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import type { Schema } from '@/domain/Schema';
import type { Subject } from '@/domain/Subject';
import type { Layout } from '@/domain/Layout';
import type { Value } from '@/domain/Value';

export interface ResolvedProperty {
	readonly propertyDefinition: PropertyDefinition;
	readonly value: Value;
}

export function resolveDisplayProperties(
	schema: Schema,
	subject: Subject,
	layout?: Layout,
): ResolvedProperty[] {
	const displayRules = layout !== undefined && layout.getSchema() === subject.getSchemaName() ?
		layout.getDisplayRules() :
		[];

	if ( displayRules.length === 0 ) {
		return resolveAllNonEmptyProperties( schema, subject );
	}

	return resolveByDisplayRules( schema, subject, displayRules );
}

function resolveAllNonEmptyProperties( schema: Schema, subject: Subject ): ResolvedProperty[] {
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
