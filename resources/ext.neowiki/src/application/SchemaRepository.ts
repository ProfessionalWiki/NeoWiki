import { InMemorySchemaLookup, SchemaLookup } from '@/application/SchemaLookup';
import { Schema } from '@neo/domain/Schema.ts';

export interface SchemaRepository extends SchemaLookup {

	saveSchema( schema: Schema ): Promise<void>;

}

export class InMemorySchemaRepository extends InMemorySchemaLookup implements SchemaRepository {

	public async saveSchema( schema: Schema ): Promise<void> {
		this.schemas.set( schema.getName(), schema );
	}

}
