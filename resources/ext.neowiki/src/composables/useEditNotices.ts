import { ref, type Ref } from 'vue';
import type { EditNotice } from '@/domain/EditNotice';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

interface EditNoticesComposable {
	notices: Ref<EditNotice[]>;
	loadNotices: ( pageId: number, schemaName?: string ) => Promise<void>;
}

/**
 * Fetches the notices to show before a Subject is edited.
 *
 * Fetched on demand rather than with the page, because a notice can depend on the viewer and on
 * state that changes without an edit, such as approval.
 */
export function useEditNotices(): EditNoticesComposable {
	const notices = ref<EditNotice[]>( [] );

	// Advisory to the last step: resolving the repository can fail too, and an editor must open
	// whether or not its notices could be fetched.
	async function loadNotices( pageId: number, schemaName?: string ): Promise<void> {
		try {
			notices.value = await NeoWikiExtension.getInstance()
				.getEditNoticeRepository()
				.getNotices( pageId, schemaName );
		} catch {
			notices.value = [];
		}
	}

	return { notices, loadNotices };
}
