import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import type { Schema } from '@/domain/Schema';
import type { Subject } from '@/domain/Subject';
import type { View } from '@/domain/View';
import type { Value } from '@/domain/Value';

export interface ResolvedProperty {
	readonly propertyDefinition: PropertyDefinition;
	readonly value: Value;
}

export function resolveDisplayProperties(
	schema: Schema,
	subject: Subject,
	view?: View,
): ResolvedProperty[] {
	const displayRules = view?.getDisplayRules() ?? [];

	if ( displayRules.length === 0 ) {
		return resolveAllNonEmptyProperties( schema, subject );
	}

	return resolveByDisplayRules( schema, subject, displayRules );
}

function resolveAllNonEmptyProperties( schema: Schema, subject: Subject ): ResolvedProperty[] {
	const result: ResolvedProperty[] = [];

	for ( const propertyDefinition of schema.getPropertyDefinitions() ) {
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
	displayRules: ReturnType<View['getDisplayRules']>,
): ResolvedProperty[] {
	const result: ResolvedProperty[] = [];

	for ( const rule of displayRules ) {
		const propertyDefinition = getPropertyDefinition( schema, rule.property );
		if ( propertyDefinition === undefined ) {
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
