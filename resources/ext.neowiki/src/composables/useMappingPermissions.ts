import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { MappingPermissionHints } from '@/application/MappingPermissionHints.ts';

export interface MappingPermissions {
	canEditMapping: Ref<boolean>;
	canDeleteMapping: Ref<boolean>;
	canCreateMappings: Ref<boolean>;
	checkEditPermission: ( mappingName: string ) => Promise<void>;
	checkDeletePermission: ( mappingName: string ) => Promise<void>;
	checkCreatePermission: () => Promise<void>;
}

export function useMappingPermissions(): MappingPermissions {
	const canEditMapping = ref( false );
	const canDeleteMapping = ref( false );
	const canCreateMappings = ref( false );
	const hints: MappingPermissionHints = NeoWikiServices.getMappingPermissionHints();

	async function checkEditPermission( mappingName: string ): Promise<void> {
		try {
			canEditMapping.value = await hints.canEditMapping( mappingName );
		} catch ( error ) {
			console.error( 'Failed to check mapping permissions:', error );
			canEditMapping.value = false;
		}
	}

	async function checkDeletePermission( mappingName: string ): Promise<void> {
		try {
			canDeleteMapping.value = await hints.canDeleteMapping( mappingName );
		} catch ( error ) {
			console.error( 'Failed to check mapping deletion permissions:', error );
			canDeleteMapping.value = false;
		}
	}

	async function checkCreatePermission(): Promise<void> {
		try {
			canCreateMappings.value = await hints.canCreateMappings();
		} catch ( error ) {
			console.error( 'Failed to check mapping creation permissions:', error );
			canCreateMappings.value = false;
		}
	}

	return {
		canEditMapping,
		canDeleteMapping,
		canCreateMappings,
		checkEditPermission,
		checkDeletePermission,
		checkCreatePermission,
	};
}
