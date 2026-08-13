import type { EditNotice } from '@/domain/EditNotice';

export interface EditNoticeRepository {
	/**
	 * Naming the Schema being edited enables Schema-scoped notices.
	 */
	getNotices( pageId: number, schemaName?: string ): Promise<EditNotice[]>;
}
