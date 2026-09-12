import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints.ts';

export interface SubjectPermissions {
	canCreateMainSubject: Ref<boolean>;
	canCreateOtherSubject: Ref<boolean>;
	canEditSubject: Ref<boolean>;
	canDeleteSubject: Ref<boolean>;
	canCreateSubjectPage: Ref<boolean>;
	checkPermissions: ( pageId: number ) => Promise<void>;
	checkCreateSubjectPagePermission: () => Promise<void>;
}

export function useSubjectPermissions(): SubjectPermissions {
	const canCreateMainSubject = ref( false );
	const canCreateOtherSubject = ref( false );
	const canEditSubject = ref( false );
	const canDeleteSubject = ref( false );
	const canCreateSubjectPage = ref( false );
	const hints: SubjectPermissionHints = NeoWikiServices.getSubjectPermissionHints();

	async function checkPermissions( pageId: number ): Promise<void> {
		try {
			const [ createMain, createOther, edit, del ] = await Promise.all( [
				hints.canCreateMainSubject(),
				hints.canCreateOtherSubject( pageId ),
				hints.canEditSubject( { text: '' } as never ),
				hints.canDeleteSubject( { text: '' } as never ),
			] );
			canCreateMainSubject.value = createMain;
			canCreateOtherSubject.value = createOther;
			canEditSubject.value = edit;
			canDeleteSubject.value = del;
		} catch ( error ) {
			console.error( 'Failed to check subject permissions:', error );
			canCreateMainSubject.value = false;
			canCreateOtherSubject.value = false;
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
		canCreateOtherSubject,
		canEditSubject,
		canDeleteSubject,
		canCreateSubjectPage,
		checkPermissions,
		checkCreateSubjectPagePermission,
	};
}
