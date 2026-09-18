export interface SubjectLabelResult {
	id: string;
	label: string;
	/** Prefixed title of the page holding the Subject, which tells namesakes apart. */
	pageTitle: string;
}

export interface SubjectLabelSearch {

	searchSubjectLabels( search: string, schema: string ): Promise<SubjectLabelResult[]>;

}
