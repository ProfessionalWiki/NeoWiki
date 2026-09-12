/**
 * The URL of `Special:Subject` for a Subject: the page that shows that one Subject, wherever it is
 * stored. Built through mw.util so it follows the wiki's URL style.
 */
export function subjectPageUrl( subjectId: string ): string {
	return mw.util.getUrl( 'Special:Subject/' + subjectId );
}
