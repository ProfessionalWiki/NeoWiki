export interface SubjectLabelResult {
	id: string;
	label: string;
	/** Prefixed title of the page holding the Subject, which tells namesakes apart. */
	pageTitle: string;
}

export interface SubjectLabelSearch {

	/**
	 * Searches the Subjects of the given Schemas, or of every Schema when given none at all. An empty
	 * list finds nothing. An empty search lists the Subjects of the given Schemas by name, and finds
	 * nothing when given none at all. Without a limit, the server's default applies.
	 */
	searchSubjectLabels(
		search: string,
		schemas: readonly string[] | undefined,
		limit?: number
	): Promise<SubjectLabelResult[]>;

}
