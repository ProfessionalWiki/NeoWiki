import { defineStore } from 'pinia';
import { createExampleSchemas } from '@/ExampleData.ts';
import { Schema } from '@neo/domain/Schema.ts';

export const useSchemaStore = defineStore( 'schema', {
	state: () => ( {
		schemas: createExampleSchemas()
	} ),
	getters: {
		getSchemas: ( state ) => state.schemas,
		getSchema: ( state ) => ( schemaName: string ): Schema => {
			const schema = state.schemas.get( schemaName );
			if ( schema === undefined ) {
				throw new Error( 'Unknown schema: ' + schemaName );
			}

			return schema as Schema;
		}
	},
	actions: {
		setSchema( name: string, schema: Schema ) {
			this.schemas.set( name, schema );
		}
	}
} );
