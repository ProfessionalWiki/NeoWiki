import type { Schema, SchemaName } from '@/domain/Schema';

export interface SchemaSummary {
	name: string;
	description: string;
	propertyCount: number;
}

export interface SchemaSummaryPage {
	schemas: SchemaSummary[];
	nextCursor: string | null;
}

export interface SchemaLookup {

	getSchema( schemaName: SchemaName ): Promise<Schema>;
	getSchemaNames( search: string ): Promise<string[]>;
	getSchemaSummaries( cursor: string | null, limit: number ): Promise<SchemaSummaryPage>;

}

export class InMemorySchemaLookup implements SchemaLookup {

	protected readonly schemas: Map<SchemaName, Schema> = new Map<SchemaName, Schema>();

	protected readonly schemaNames: Set<string> = new Set<string>();

	public constructor( schemas: Schema[] ) {
		for ( const schema of schemas ) {
			this.schemas.set( schema.getName(), schema );
			this.schemaNames.add( schema.getName() );
		}
	}

	public async getSchema( schemaName: SchemaName ): Promise<Schema> {
		if ( !this.schemas.has( schemaName ) ) {
			throw new Error( `Schema ${ schemaName } not found` );
		}
		return this.schemas.get( schemaName ) as Schema;
	}

	public async getSchemaNames(): Promise<string[]> {
		return [ ...this.schemaNames ];
	}

	public async getSchemaSummaries( cursor: string | null, limit: number ): Promise<SchemaSummaryPage> {
		const summaries = [ ...this.schemas.values() ].map( ( schema ) => ( {
			name: schema.getName(),
			description: schema.getDescription(),
			propertyCount: [ ...schema.getPropertyDefinitions() ].length,
		} ) );

		// The cursor is opaque to callers; this fake encodes the next start index in it.
		const start = cursor === null ? 0 : parseInt( cursor, 10 );
		const end = start + limit;

		return {
			schemas: summaries.slice( start, end ),
			nextCursor: end < summaries.length ? String( end ) : null,
		};
	}

	public clearSchemas(): void {
		this.schemas.clear();
	}

}
