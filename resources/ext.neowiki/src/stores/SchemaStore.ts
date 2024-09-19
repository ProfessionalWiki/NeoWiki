import { defineStore } from 'pinia';
import { Schema } from '@neo/domain/Schema.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';

export const useSchemaStore = defineStore( 'schema', {
	// TODO: testing data
	state: () => ( {
		schemas: [
			new Schema(
				'Person',
				'Information about an individual',
				new PropertyDefinitionList( [] )
			),
			new Schema(
				'Organization',
				'Details about a company or institution',
				new PropertyDefinitionList( [] )
			),
			new Schema(
				'Place',
				'Geographic location or landmark',
				new PropertyDefinitionList( [] )
			)
		]
	} ),
	getters: {
		getSchemas: ( state ) => state.schemas
	}
} );
