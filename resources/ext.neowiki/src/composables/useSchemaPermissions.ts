import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

export interface SchemaPermissions {
	canEditSchema: Ref<boolean>;
	canCreateSchemas: Ref<boolean>;
	checkEditPermission: ( schemaName: string ) => Promise<void>;
	checkCreatePermission: () => Promise<void>;
}

export function useSchemaPermissions(): SchemaPermissions {
	const canEditSchema = ref( false );
	const canCreateSchemas = ref( false );

	async function checkEditPermission( schemaName: string ): Promise<void> {
		try {
			canEditSchema.value = await NeoWikiServices.getSchemaAuthorizer().canEditSchema( schemaName );
		} catch ( error ) {
			console.error( 'Failed to check schema permissions:', error );
			canEditSchema.value = false;
		}
	}

	async function checkCreatePermission(): Promise<void> {
		try {
			canCreateSchemas.value = await NeoWikiServices.getSchemaAuthorizer().canCreateSchemas();
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
