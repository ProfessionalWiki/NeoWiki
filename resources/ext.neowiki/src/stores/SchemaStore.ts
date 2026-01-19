import { defineStore } from 'pinia';
import { Schema } from '@/domain/Schema.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

export const useSchemaStore = defineStore( 'schema', {
	state: () => ( {
		schemas: new Map<string, Schema>(),
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
		async searchAndFetchMissingSchemas( search: string ): Promise<string[]> {
			const schemaNames = await NeoWikiExtension.getInstance().getSchemaRepository().getSchemaNames( search );
			await Promise.all( schemaNames.map( ( name ) => this.getOrFetchSchema( name ) ) );
			return schemaNames;
		},
		async saveSchema( schema: Schema ): Promise<void> {
			await NeoWikiExtension.getInstance().getSchemaRepository().saveSchema( schema );
			this.setSchema( schema.getName(), schema );
		},
	},
} );
