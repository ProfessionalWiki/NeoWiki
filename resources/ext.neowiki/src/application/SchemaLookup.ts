import type { Schema, SchemaName } from '@neo/domain/Schema';

export interface SchemaLookup {

	getSchema( schemaName: SchemaName ): Promise<Schema>;
	getSchemaNames( search: string ): Promise<string[]>;

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

	public clearSchemas(): void {
		this.schemas.clear();
	}

}
