import { defineStore } from 'pinia';
import { Schema } from '@neo/domain/Schema.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

export const useSchemaStore = defineStore( 'schema', {
	state: () => ( {
		schemas: new Map<string, Schema>()
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
		setSchema( name: string, schema: Schema ): void {
			this.schemas.set( name, schema );
		},
		async fetchSchema( name: string ): Promise<void> {
			const schema = await NeoWikiExtension.getInstance().getSchemaRepository().getSchema( name );
			this.setSchema( name, schema );
		}
	}
} );
