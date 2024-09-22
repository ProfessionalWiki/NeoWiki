import { defineStore } from 'pinia';
import { createExampleSchemas } from '@/ExampleData.ts';

export const useSchemaStore = defineStore( 'schema', {
	state: () => ( {
		schemas: createExampleSchemas()
	} ),
	getters: {
		getSchemas: ( state ) => state.schemas
	}
} );
