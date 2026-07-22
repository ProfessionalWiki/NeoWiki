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

export const useSchemaStore = defineStore( 'schema', {
	state: () => ( {
		schemas: new Map<string, Schema>(),
		allSummaries: null as SchemaSummary[] | null,
		summariesRequest: null as Promise<SchemaSummary[]> | null,
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
		async fetchSchema( name: string ): Promise<void> {
			const schema = await NeoWikiExtension.getInstance().getSchemaRepository().getSchema( name );
			this.setSchema( name, schema );
		},
		async getOrFetchSchema( name: string ): Promise<Schema> {
			if ( !this.schemas.has( name ) ) {
				await this.fetchSchema( name );
			}
			return this.getSchema( name );
		},
		// Loads every Schema summary (name + description) once and caches it so the
		// schema picker can show the full list and filter client-side. The cache is
		// cleared on saveSchema. Concurrent callers (e.g. several relation-property
		// pickers mounting in the same render) share one in-flight request rather
		// than each running a full pagination.
		async getAllSchemaSummaries(): Promise<SchemaSummary[]> {
			if ( this.allSummaries !== null ) {
				return this.allSummaries;
			}

			if ( this.summariesRequest === null ) {
				this.summariesRequest = this.fetchAllSchemaSummaries();
			}

			return this.summariesRequest;
		},
		// Pages through the summaries endpoint (capped at 50) by following the response's
		// cursor until it is null. The cursor, not the page length, decides whether more
		// pages follow: a page can come back shorter than requested when a readable Schema
		// fails to load (malformed). The in-flight request is released on completion so a
		// later load (after the cache is cleared, or after a failure) starts fresh.
		async fetchAllSchemaSummaries(): Promise<SchemaSummary[]> {
			const repository = NeoWikiExtension.getInstance().getSchemaRepository();
			const pageSize = 50;
			const summaries: SchemaSummary[] = [];

			try {
				let cursor: string | null = null;

				do {
					const page = await repository.getSchemaSummaries( cursor, pageSize );
					summaries.push( ...page.schemas );
					cursor = page.nextCursor;
				} while ( cursor !== null );

				this.allSummaries = summaries;
				return summaries;
			} finally {
				this.summariesRequest = null;
			}
		},
		// Checks existence via the schema-names search (a 200 response) rather
		// than getOrFetchSchema, which 404s for a missing name — those 404s are
		// avoidable console/network noise when checking a not-yet-created name.
		// The name is normalised so e.g. "person" or "Foo_Bar" matches the
		// existing "Person" / "Foo Bar" the same way a save would.
		async schemaNameExists( name: string ): Promise<boolean> {
			const normalized = normalizeSchemaName( name );
			const matches = await NeoWikiExtension.getInstance().getSchemaRepository().getSchemaNames( normalized );
			return matches.some( ( match ) => normalizeSchemaName( match ) === normalized );
		},
		async saveSchema( schema: Schema, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSchemaRepository().saveSchema( schema, comment );
			this.setSchema( schema.getName(), schema );
			this.allSummaries = null;
			this.summariesRequest = null;
		},
	},
} );
