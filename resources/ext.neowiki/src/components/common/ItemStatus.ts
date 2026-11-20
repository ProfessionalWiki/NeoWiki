/** What a list item's mark says about what the item stands for. An error carries the message that explains it. */
export type ItemStatus = { kind: 'unsaved' } | { kind: 'error'; message: string };

export const UNSAVED: ItemStatus = { kind: 'unsaved' };

export function errorStatus( message: string ): ItemStatus {
	return { kind: 'error', message };
}

/** The status in words: an error's message, or what the unsaved mark means. */
export function statusText( status: ItemStatus ): string {
	return status.kind === 'error' ? status.message : mw.msg( 'neowiki-subject-editor-unsaved' );
}
