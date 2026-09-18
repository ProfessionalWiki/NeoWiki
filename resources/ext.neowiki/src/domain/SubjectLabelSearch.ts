export interface SubjectLabelResult {
	id: string;
	label: string;
}

export interface SubjectLabelSearch {

	/** Without a schema, Subjects of every Schema are searched. */
	searchSubjectLabels( search: string, schema?: string ): Promise<SubjectLabelResult[]>;

}
