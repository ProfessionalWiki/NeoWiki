export interface SubjectLabelResult {
	id: string;
	label: string;
}

export interface SubjectLabelSearch {

	searchSubjectLabels( search: string, schema: string ): Promise<SubjectLabelResult[]>;

}
