import { ref, type Ref } from 'vue';
import type { EditNotice } from '@/domain/EditNotice';
import type { EditNoticeRepository } from '@/application/EditNoticeRepository';

interface EditNoticesComposable {
	notices: Ref<EditNotice[]>;
	/** Whether the newest request has yet to settle. */
	loading: Ref<boolean>;
	loadNotices: ( pageId: number, schemaName?: string ) => Promise<void>;
}

/**
 * Fetches the notices to show before a Subject is edited.
 *
 * Fetched on demand rather than with the page, because a notice can depend on the viewer and on
 * state that changes without an edit, such as approval.
 */
/**
 * The repository is resolved per call rather than passed ready-made: building it reaches the
 * extension singleton, which must not happen while a component is setting up.
 */
export function useEditNotices( resolveRepository: () => EditNoticeRepository ): EditNoticesComposable {
	const notices = ref<EditNotice[]>( [] );
	const loading = ref( false );
	// The creator refetches whenever the Schema changes, so two selections can be in flight at once.
	// Only the newest request may write, or a slower earlier one lands on top of it.
	let latestRequest = 0;

	async function loadNotices( pageId: number, schemaName?: string ): Promise<void> {
		const request = ++latestRequest;
		loading.value = true;
		// At once: a host can stop waiting before the answer, and must not show the last request's meanwhile.
		notices.value = [];
		let fetched: EditNotice[] = [];

		try {
			fetched = await resolveRepository().getNotices( pageId, schemaName );
		} catch {
			// Advisory to the last step: an editor must open whether or not its notices could be fetched.
		}

		if ( request === latestRequest ) {
			notices.value = fetched;
			loading.value = false;
		}
	}

	return { notices, loading, loadNotices };
}
