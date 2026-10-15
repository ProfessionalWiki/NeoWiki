/** Whether the reader may see how many Subjects each Schema has. */
export function areSubjectCountsAvailable(): boolean {
	return mw.config.get( 'wgNeoWikiSubjectCountsAvailable' ) === true;
}
