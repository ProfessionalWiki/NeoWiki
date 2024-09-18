import { defineStore } from 'pinia';

interface Schema {
	name: string;
	description: string;
	properties: [];
}

export const useSchemaStore = defineStore( 'schema', {
	// TODO: testing data
	state: (): { schemas: Schema[] } => ( {
		schemas: [
			{
				name: 'Person',
				description: 'Information about an individual',
				properties: []
			},
			{
				name: 'Organization',
				description: 'Details about a company or institution',
				properties: []
			},
			{
				name: 'Place',
				description: 'Geographic location or landmark',
				properties: []
			}
		]
	} ),
	getters: {
		getSchemas: ( state ): Schema[] => state.schemas
	}
} );
