import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { LayoutPermissionHints } from '@/application/LayoutPermissionHints.ts';

export interface LayoutPermissions {
	canEditLayout: Ref<boolean>;
	canCreateLayouts: Ref<boolean>;
	checkEditPermission: ( layoutName: string ) => Promise<void>;
	checkCreatePermission: () => Promise<void>;
}

export function useLayoutPermissions(): LayoutPermissions {
	const canEditLayout = ref( false );
	const canCreateLayouts = ref( false );
	const hints: LayoutPermissionHints = NeoWikiServices.getLayoutPermissionHints();

	async function checkEditPermission( layoutName: string ): Promise<void> {
		try {
			canEditLayout.value = await hints.canEditLayout( layoutName );
		} catch ( error ) {
			console.error( 'Failed to check layout permissions:', error );
			canEditLayout.value = false;
		}
	}

	async function checkCreatePermission(): Promise<void> {
		try {
			canCreateLayouts.value = await hints.canCreateLayouts();
		} catch ( error ) {
			console.error( 'Failed to check layout creation permissions:', error );
			canCreateLayouts.value = false;
		}
	}

	return {
		canEditLayout,
		canCreateLayouts,
		checkEditPermission,
		checkCreatePermission,
	};
}
