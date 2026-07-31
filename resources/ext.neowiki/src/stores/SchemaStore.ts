import { defineStore } from 'pinia';
import { Schema } from '@/domain/Schema.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';

/**
 * Approximates MediaWiki title normalisation for a Schema name (schemas are
 * wiki pages) so a duplicate-name check resolves to the same page a save would:
 * trims, turns underscores into spaces, collapses runs of whitespace, and
 * upper-cases the first character. The save remains the authoritative guard.
 */
export function normalizeSchemaName( name: string ): string {
	const collapsed = name.trim().replace( /[\s_]+/g, ' ' );
	return collapsed.charAt( 0 ).toUpperCase() + collapsed.slice( 1 );
}

// Pages through the summaries endpoint (capped at 50) by following the response's
// cursor until it is null. The cursor, not the page length, decides whether more
// pages follow: a page can come back shorter than requested when a readable Schema
// fails to load (malformed). Deliberately not a store action: it neither reads store state nor manages
// the in-flight slot, so exposing it alongside fetchAllSchemaSummaries would
// let callers page the whole endpoint outside the dedup/detach machinery
// that action provides.
async function pageThroughSchemaSummaries(): Promise<SchemaSummary[]> {
	const repository = NeoWikiExtension.getInstance().getSchemaRepository();
	const pageSize = 50;
	const summaries: SchemaSummary[] = [];
	let cursor: string | null = null;

	do {
		const page = await repository.getSchemaSummaries( cursor, pageSize );
		summaries.push( ...page.schemas );
		cursor = page.nextCursor;
	} while ( cursor !== null );

	return summaries;
}

export const useSchemaStore = defineStore( 'schema', {
	state: () => ( {
		schemas: new Map<string, Schema>(),
		summariesRequest: null as Promise<SchemaSummary[]> | null,
		// Bumped when a write is acknowledged (save or removal). Async reads snapshot it
		// before their await and discard the write-back when it moved: a response that
		// raced a mutation may predate that mutation. Store-wide by design — discarding
		// an unrelated in-flight fetch costs one refetch and keeps the rule simple.
		mutationEpoch: 0,
	} ),
	getters: {
		getSchemas: ( state ) => state.schemas,
		getSchema: ( state ) => ( schemaName: string ): Schema => {
			const schema = state.schemas.get( schemaName );
			if ( schema === undefined ) {
				throw new Error( 'Unknown schema: ' + schemaName );
			}

			return schema as Schema;
		},
	},
	actions: {
		setSchema( name: string, schema: Schema ): void { // TODO: just take Schema
			this.schemas.set( name, schema );
		},
		// Always fetches. Concurrent callers (e.g. several relation-property pickers
		// mounting in the same render) share one in-flight pagination rather than each
		// running a full one; results are not retained, so each new caller cohort gets
		// fresh data and pickers never show session-stale summaries.
		async fetchAllSchemaSummaries(): Promise<SchemaSummary[]> {
			if ( this.summariesRequest === null ) {
				this.summariesRequest = pageThroughSchemaSummaries();
			}

			const request = this.summariesRequest;

			try {
				return await request;
			} finally {
				if ( this.summariesRequest === request ) {
					this.summariesRequest = null;
				}
			}
		},
		// Checks existence via the schema-names search (a 200 response) rather
		// than a schema fetch, which 404s for a missing name — those 404s are
		// avoidable console/network noise when checking a not-yet-created name.
		// The name is normalised so e.g. "person" or "Foo_Bar" matches the
		// existing "Person" / "Foo Bar" the same way a save would.
		async schemaNameExists( name: string ): Promise<boolean> {
			const normalized = normalizeSchemaName( name );
			const matches = await NeoWikiExtension.getInstance().getSchemaRepository().getSchemaNames( normalized );
			return matches.some( ( match ) => normalizeSchemaName( match ) === normalized );
		},
		async saveSchema( schema: Schema, comment?: string ): Promise<void> {
			try {
				await NeoWikiExtension.getInstance().getSchemaRepository().saveSchema( schema, comment );
				this.mutationEpoch++;
				this.setSchema( schema.getName(), schema );
			} finally {
				// Even a save whose reply was lost may have committed: detach any in-flight
				// summaries pagination so the next picker mount fetches fresh instead of
				// joining a pre-save request.
				this.summariesRequest = null;
			}
		},
		removeSchema( name: string ): void {
			this.mutationEpoch++;
			this.schemas.delete( name );
			this.summariesRequest = null;
		},
	},
} );
