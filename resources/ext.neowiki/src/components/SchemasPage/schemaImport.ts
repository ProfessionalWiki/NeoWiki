import type { Schema } from '@/domain/Schema.ts';
import { normalizeSchemaName } from '@/stores/SchemaStore.ts';

export interface SchemaImportItem {
	readonly schema: Schema;
	readonly replacesExisting: boolean;
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
 * A Schema replaces an existing one when its name resolves to the same page title as one of the
 * existing names, or as a Schema before it in the list, which importing that one creates.
 */
export function planSchemaImport( schemas: Schema[], existingNames: string[] ): SchemaImportItem[] {
	const existing = new Set( existingNames.map( normalizeSchemaName ) );

	return schemas.map( ( schema ) => {
		const name = normalizeSchemaName( schema.getName() );
		const replacesExisting = existing.has( name );
		existing.add( name );

		return { schema, replacesExisting };
	} );
}

/**
 * Saves the Schemas one at a time, in the order given. A failed save does not stop the others.
 */
export async function importSchemas(
	items: SchemaImportItem[],
	save: ( schema: Schema ) => Promise<void>,
): Promise<SchemaImportOutcome> {
	const outcome: SchemaImportOutcome = { created: [], replaced: [], failed: [] };

	for ( const { schema, replacesExisting } of items ) {
		try {
			await save( schema );
			( replacesExisting ? outcome.replaced : outcome.created ).push( schema.getName() );
		} catch ( error ) {
			outcome.failed.push( {
				name: schema.getName(),
				reason: error instanceof Error ? error.message : String( error ),
			} );
		}
	}

	return outcome;
}
