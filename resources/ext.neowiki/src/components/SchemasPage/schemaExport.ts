// A Schema export holds each Schema under its name, in the Schema page format
// (docs/api/schema-format.md) as the Schema editor writes it: { "schemas": { "<name>": { ... } } }.

import type { Schema } from '@/domain/Schema.ts';
import type { SchemaLookup } from '@/application/SchemaLookup.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';

/**
 * Rejects if any of the Schemas fails to load, rather than writing a file without it.
 */
export async function exportSchemas( names: string[], schemaLookup: Pick<SchemaLookup, 'getSchema'> ): Promise<string> {
	const schemas = await Promise.all( names.map( ( name ) => loadSchema( name, schemaLookup ) ) );
	const serializer = new SchemaSerializer();

	return JSON.stringify(
		{
			schemas: Object.fromEntries(
				schemas.map( ( schema ) => [ schema.getName(), serializer.schemaToJson( schema ) ] ),
			),
		},
		null,
		4,
	);
}

async function loadSchema( name: string, schemaLookup: Pick<SchemaLookup, 'getSchema'> ): Promise<Schema> {
	try {
		return await schemaLookup.getSchema( name );
	} catch ( _error ) {
		throw new Error( mw.msg( 'neowiki-schemas-export-error-unreadable-schema', name ) );
	}
}

/**
 * Throws an Error saying what is wrong with a file it cannot read, naming the entry at fault where
 * there is one.
 */
export function parseSchemaExport( text: string ): Schema[] {
	const entries = Object.entries( schemasIn( parseJson( text ) ) );

	if ( entries.length === 0 ) {
		throw new Error( mw.msg( 'neowiki-schemas-import-error-empty' ) );
	}

	return entries.map( ( [ name, json ] ) => readSchema( name, json ) );
}

function parseJson( text: string ): unknown {
	try {
		return JSON.parse( text );
	} catch ( _error ) {
		throw new Error( mw.msg( 'neowiki-schemas-import-error-not-json' ) );
	}
}

function isJsonObject( value: unknown ): value is Record<string, unknown> {
	return typeof value === 'object' && value !== null && !Array.isArray( value );
}

function schemasIn( file: unknown ): Record<string, unknown> {
	if ( !isJsonObject( file ) || !isJsonObject( file.schemas ) ) {
		throw new Error( mw.msg( 'neowiki-schemas-import-error-no-schemas-object' ) );
	}

	return file.schemas;
}

function readSchema( name: string, json: unknown ): Schema {
	if ( !isJsonObject( json ) || !isJsonObject( json.propertyDefinitions ) ) {
		throw new Error( mw.msg( 'neowiki-schemas-import-error-no-property-definitions', name ) );
	}

	try {
		return new SchemaDeserializer().deserialize( name, json );
	} catch ( error ) {
		throw new Error( mw.msg(
			'neowiki-schemas-import-error-unreadable-schema',
			name,
			error instanceof Error ? error.message : String( error ),
		) );
	}
}
