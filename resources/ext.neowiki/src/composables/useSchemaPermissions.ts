import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';

export interface SchemaPermissions {
	canEditSchema: Ref<boolean>;
	canCreateSchemas: Ref<boolean>;
	checkEditPermission: ( schemaName: string ) => Promise<void>;
	checkCreatePermission: () => Promise<void>;
}

export function useSchemaPermissions(): SchemaPermissions {
	const canEditSchema = ref( false );
	const canCreateSchemas = ref( false );
	const authorizer: SchemaAuthorizer = NeoWikiServices.getSchemaAuthorizer();

	async function checkEditPermission( schemaName: string ): Promise<void> {
		try {
			canEditSchema.value = await authorizer.canEditSchema( schemaName );
		} catch ( error ) {
			console.error( 'Failed to check schema permissions:', error );
			canEditSchema.value = false;
		}
	}

	async function checkCreatePermission(): Promise<void> {
		try {
			canCreateSchemas.value = await authorizer.canCreateSchemas();
		} catch ( error ) {
			console.error( 'Failed to check schema creation permissions:', error );
			canCreateSchemas.value = false;
		}
	}

	return {
		canEditSchema,
		canCreateSchemas,
		checkEditPermission,
		checkCreatePermission,
	};
}
