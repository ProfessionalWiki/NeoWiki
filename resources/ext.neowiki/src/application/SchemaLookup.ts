import type { Schema, SchemaName } from '@/domain/Schema';

export interface SchemaSummary {
	name: string;
	description: string;
	propertyCount: number;
}

export interface SchemaSummaryPage {
	schemas: SchemaSummary[];
	totalRows: number;
}

export interface SchemaLookup {

	getSchema( schemaName: SchemaName ): Promise<Schema>;
	getSchemaNames( search: string ): Promise<string[]>;
	getSchemaSummaries( offset: number, limit: number ): Promise<SchemaSummaryPage>;

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

	public async getSchemaSummaries( offset: number, limit: number ): Promise<SchemaSummaryPage> {
		const summaries = [ ...this.schemas.values() ].map( ( schema ) => ( {
			name: schema.getName(),
			description: schema.getDescription(),
			propertyCount: [ ...schema.getPropertyDefinitions() ].length,
		} ) );

		return {
			schemas: summaries.slice( offset, offset + limit ),
			totalRows: summaries.length,
		};
	}

	public clearSchemas(): void {
		this.schemas.clear();
	}

}
