/**
 * Whether the reader may see how many Subjects each Schema has, as `wgNeoWikiSubjectCountsAvailable` reaches the
 * frontend: the counts read the Neo4j projection, and only a reader who may run queries gets them.
 */
export function areSubjectCountsAvailable(): boolean {
	return mw.config.get( 'wgNeoWikiSubjectCountsAvailable' ) === true;
}
