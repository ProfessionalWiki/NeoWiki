// Object keys are sorted so that equal content serializes equally, except where their order means something:
// a Schema's property order is the order its properties are shown in. Arrays keep their order throughout.

import { cyrb53 } from './cyrb53';
import type { DependentSubjectEntry, JsonObject, SubjectEntry } from './ModelBundle';

export function isJsonObject( value: unknown ): value is JsonObject {
	return typeof value === 'object' && value !== null && !Array.isArray( value );
}

function sortedKeys( value: unknown, keepsKeyOrderAt: readonly string[] | null, path: readonly string[] ): unknown {
	if ( Array.isArray( value ) ) {
		return value.map( ( item ) => sortedKeys( item, keepsKeyOrderAt, [ ...path, '' ] ) );
	}

	if ( !isJsonObject( value ) ) {
		return value;
	}

	const keys = Object.keys( value ).filter( ( key ) => value[ key ] !== undefined );

	if ( keepsKeyOrderAt === null || path.join( '/' ) !== keepsKeyOrderAt.join( '/' ) ) {
		keys.sort();
	}

	return Object.fromEntries( keys.map( ( key ) => [ key, sortedKeys( value[ key ], keepsKeyOrderAt, [ ...path, key ] ) ] ) );
}

export function canonicalJson( value: JsonObject ): JsonObject {
	return sortedKeys( value, null, [] ) as JsonObject;
}

export function schemaItem( schema: JsonObject ): JsonObject {
	return sortedKeys( schema, [ 'propertyDefinitions' ], [] ) as JsonObject;
}

export function subjectsAreDependent( schema: JsonObject ): boolean {
	return schema.subjectsAreDependent === true;
}

/**
 * Relation IDs are left out: they are local handles, which a write mints anew.
 */
function statementsItem( statements: JsonObject ): JsonObject {
	return canonicalJson( Object.fromEntries(
		Object.entries( statements )
			.filter( ( [ , statement ] ) => statement !== null )
			.map( ( [ name, statement ] ) => [ name, withoutRelationIds( statement ) ] ),
	) );
}

function withoutRelationIds( statement: unknown ): unknown {
	if ( !isJsonObject( statement ) || !Array.isArray( statement.value ) ) {
		return statement;
	}

	return {
		...statement,
		value: statement.value.map( ( part ) => isJsonObject( part ) && typeof part.target === 'string' ?
			{ ...part, id: undefined } :
			part ),
	};
}

function dependentSubjectItem( entry: DependentSubjectEntry ): JsonObject {
	return { id: entry.id, schema: entry.schema, statements: statementsItem( entry.statements ) };
}

/**
 * What a write of the Subject sets: its page title is not part of it.
 */
export function subjectItem( entry: SubjectEntry ): JsonObject {
	const dependentSubjects = [ ...entry.dependentSubjects ]
		.sort( ( a, b ) => a.id < b.id ? -1 : 1 )
		.map( dependentSubjectItem );

	return canonicalJson( {
		schema: entry.schema,
		label: normalizedLabel( entry.label ) ?? undefined,
		statements: statementsItem( entry.statements ),
		dependentSubjects: dependentSubjects.length > 0 ? dependentSubjects : undefined,
	} );
}

export function normalizedLabel( label: string | null ): string | null {
	const trimmed = label?.trim() ?? '';
	return trimmed === '' ? null : trimmed;
}

export function itemHash( item: JsonObject ): string {
	return cyrb53( JSON.stringify( item ) );
}

export function itemText( item: JsonObject ): string {
	return JSON.stringify( item, null, 4 );
}
