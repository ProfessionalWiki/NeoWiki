import type { SchemaLookup } from '@/application/SchemaLookup';
import { Schema } from '@neo/domain/Schema.ts';

export interface SchemaRepository extends SchemaLookup {

	saveSchema( schema: Schema ): Promise<void>;

}
