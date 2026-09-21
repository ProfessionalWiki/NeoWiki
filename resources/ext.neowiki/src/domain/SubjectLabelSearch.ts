export interface SubjectLabelResult {
	id: string;
	label: string;
}

export interface SubjectLabelSearch {

	/** Undefined as the schema searches Subjects of every Schema. */
	searchSubjectLabels( search: string, schema: string | undefined ): Promise<SubjectLabelResult[]>;

}
