/**
 * Whether this wiki puts Subjects before pages (ADR 33), as
 * `$wgNeoWikiSubjectFirst` reaches the frontend. False is page-first, the default, which a wiki
 * that says nothing also gets.
 */
export function isSubjectFirst(): boolean {
	return mw.config.get( 'wgNeoWikiSubjectFirst' ) === true;
}
