import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints.ts';

export interface SubjectPermissions {
	canCreateMainSubject: Ref<boolean>;
	canCreateChildSubject: Ref<boolean>;
	canEditSubject: Ref<boolean>;
	canDeleteSubject: Ref<boolean>;
	canCreateSubjectPage: Ref<boolean>;
	checkPermissions: ( pageId: number ) => Promise<void>;
	checkCreateSubjectPagePermission: () => Promise<void>;
}

export function useSubjectPermissions(): SubjectPermissions {
	const canCreateMainSubject = ref( false );
	const canCreateChildSubject = ref( false );
	const canEditSubject = ref( false );
	const canDeleteSubject = ref( false );
	const canCreateSubjectPage = ref( false );
	const hints: SubjectPermissionHints = NeoWikiServices.getSubjectPermissionHints();

	async function checkPermissions( pageId: number ): Promise<void> {
		try {
			const [ createMain, createChild, edit, del ] = await Promise.all( [
				hints.canCreateMainSubject( pageId ),
				hints.canCreateChildSubject( pageId ),
				hints.canEditSubject( pageId ),
				hints.canDeleteSubject( pageId ),
			] );
			canCreateMainSubject.value = createMain;
			canCreateChildSubject.value = createChild;
			canEditSubject.value = edit;
			canDeleteSubject.value = del;
		} catch ( error ) {
			console.error( 'Failed to check subject permissions:', error );
			canCreateMainSubject.value = false;
			canCreateChildSubject.value = false;
			canEditSubject.value = false;
			canDeleteSubject.value = false;
		}
	}

	async function checkCreateSubjectPagePermission(): Promise<void> {
		try {
			canCreateSubjectPage.value = await hints.canCreateSubjectPage();
		} catch ( error ) {
			console.error( 'Failed to check subject page creation permission:', error );
			canCreateSubjectPage.value = false;
		}
	}

	return {
		canCreateMainSubject,
		canCreateChildSubject,
		canEditSubject,
		canDeleteSubject,
		canCreateSubjectPage,
		checkPermissions,
		checkCreateSubjectPagePermission,
	};
}
