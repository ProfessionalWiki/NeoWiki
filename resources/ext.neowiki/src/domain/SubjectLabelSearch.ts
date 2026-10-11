export interface SubjectLabelResult {
	id: string;
	label: string;
}

export interface SubjectLabelSearch {

	/**
	 * Searches the Subjects of the given Schemas, or of every Schema when given none at all. An empty
	 * list finds nothing.
	 */
	searchSubjectLabels( search: string, schemas: readonly string[] | undefined ): Promise<SubjectLabelResult[]>;

}
