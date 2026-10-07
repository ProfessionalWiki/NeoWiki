export interface SubjectCountLookup {
	/** By Schema name; a Schema without Subjects is absent. Subjects on pages the reader may not read count too. */
	getSubjectCounts(): Promise<Map<string, number>>;
}
