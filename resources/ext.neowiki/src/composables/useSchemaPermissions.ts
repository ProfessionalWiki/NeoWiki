import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

export interface SchemaPermissions {
	canEditSchema: Ref<boolean>;
	checkPermission: ( schemaName: string ) => Promise<void>;
}

export function useSchemaPermissions(): SchemaPermissions {
	const canEditSchema = ref( false );

	async function checkPermission( schemaName: string ): Promise<void> {
		try {
			canEditSchema.value = await NeoWikiServices.getSchemaAuthorizer().canEditSchema( schemaName );
		} catch ( error ) {
			console.error( 'Failed to check schema permissions:', error );
			canEditSchema.value = false;
		}
	}

	return {
		canEditSchema,
		checkPermission,
	};
}
