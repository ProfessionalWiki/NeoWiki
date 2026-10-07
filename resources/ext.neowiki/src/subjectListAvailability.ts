/**
 * Whether this wiki can list its Subjects, as `wgNeoWikiSubjectListAvailable` reaches the frontend: the list reads
 * the Neo4j projection, which not every wiki has.
 */
export function isSubjectListAvailable(): boolean {
	return mw.config.get( 'wgNeoWikiSubjectListAvailable' ) === true;
}
