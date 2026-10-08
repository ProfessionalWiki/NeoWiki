import type { Schema } from '@/domain/Schema.ts';
import type { SchemaLookup } from '@/application/SchemaLookup.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { normalizeSchemaName } from '@/stores/SchemaStore.ts';

export type SchemaImportStatus = 'new' | 'changed' | 'unchanged';

export interface SchemaImportItem {
	readonly schema: Schema;
	readonly status: SchemaImportStatus;
}

interface SchemaImportFailure {
	readonly name: string;
	readonly reason: string;
}

export interface SchemaImportOutcome {
	readonly created: string[];
	readonly replaced: string[];
	readonly failed: SchemaImportFailure[];
}

/**
 * A Schema is new when its name resolves to the page title of no existing Schema and of no Schema
 * before it in the list. Otherwise it is unchanged when it equals the wiki's Schema, and changed when
 * it differs, follows a Schema of the same title in the list, or the wiki's Schema fails to load.
 */
export async function planSchemaImport(
	schemas: Schema[],
	existingNames: string[],
	schemaLookup: Pick<SchemaLookup, 'getSchema'>,
): Promise<SchemaImportItem[]> {
	const existing = new Map( existingNames.map( ( name ) => [ normalizeSchemaName( name ), name ] ) );
	const listed = new Set<string>();

	return Promise.all( schemas.map( async ( schema ): Promise<SchemaImportItem> => {
		const name = normalizeSchemaName( schema.getName() );
		const listedBefore = listed.has( name );
		listed.add( name );

		if ( listedBefore ) {
			return { schema, status: 'changed' };
		}

		const existingName = existing.get( name );

		if ( existingName === undefined ) {
			return { schema, status: 'new' };
		}

		return { schema, status: await compareWithWiki( schema, existingName, schemaLookup ) };
	} ) );
}

async function compareWithWiki(
	schema: Schema,
	existingName: string,
	schemaLookup: Pick<SchemaLookup, 'getSchema'>,
): Promise<SchemaImportStatus> {
	try {
		const serializer = new SchemaSerializer();
		const wikiSchema = await schemaLookup.getSchema( existingName );

		return serializer.serializeSchema( wikiSchema ) === serializer.serializeSchema( schema ) ? 'unchanged' : 'changed';
	} catch ( _error ) {
		return 'changed';
	}
}

/**
 * Saves the Schemas one at a time, in the order given. A failed save does not stop the others.
 */
export async function importSchemas(
	items: SchemaImportItem[],
	save: ( schema: Schema ) => Promise<void>,
): Promise<SchemaImportOutcome> {
	const outcome: SchemaImportOutcome = { created: [], replaced: [], failed: [] };

	for ( const { schema, status } of items ) {
		try {
			await save( schema );
			( status === 'new' ? outcome.created : outcome.replaced ).push( schema.getName() );
		} catch ( error ) {
			outcome.failed.push( {
				name: schema.getName(),
				reason: error instanceof Error ? error.message : String( error ),
			} );
		}
	}

	return outcome;
}
