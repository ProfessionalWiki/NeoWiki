import { ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';

export interface SubjectPermissions {
	canCreateMainSubject: Ref<boolean>;
	canCreateChildSubject: Ref<boolean>;
	canEditSubject: Ref<boolean>;
	canDeleteSubject: Ref<boolean>;
	checkPermissions: ( pageId: number ) => Promise<void>;
}

export function useSubjectPermissions(): SubjectPermissions {
	const canCreateMainSubject = ref( false );
	const canCreateChildSubject = ref( false );
	const canEditSubject = ref( false );
	const canDeleteSubject = ref( false );
	const authorizer: SubjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();

	async function checkPermissions( pageId: number ): Promise<void> {
		try {
			const [ createMain, createChild, edit, del ] = await Promise.all( [
				authorizer.canCreateMainSubject(),
				authorizer.canCreateChildSubject( pageId ),
				authorizer.canEditSubject( { text: '' } as never ),
				authorizer.canDeleteSubject( { text: '' } as never ),
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

	return {
		canCreateMainSubject,
		canCreateChildSubject,
		canEditSubject,
		canDeleteSubject,
		checkPermissions,
	};
}
