export interface SubjectLabelResult {
	id: string;
	label: string;
	/** Prefixed title of the page holding the Subject, which tells namesakes apart. */
	pageTitle: string;
}

export interface SubjectLabelSearch {

	/** Undefined as the schema searches Subjects of every Schema. */
	searchSubjectLabels( search: string, schema: string | undefined ): Promise<SubjectLabelResult[]>;

}
