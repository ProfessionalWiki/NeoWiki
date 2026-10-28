/**
 * The URL of a Subject's row on the Data tab of the page it is stored on: where a page-first wiki
 * sends a reader who follows a relation (ADR 33). The fragment is the bare Subject id, the deep-link
 * anchor the Data tab resolves to a row (see subjectIdFromHash). Built through mw.util so it follows
 * the wiki's URL style.
 */
export function subjectRowUrl( pageName: string, subjectId: string ): string {
	return mw.util.getUrl( pageName, { action: 'subjects' } ) + '#' + subjectId;
}
