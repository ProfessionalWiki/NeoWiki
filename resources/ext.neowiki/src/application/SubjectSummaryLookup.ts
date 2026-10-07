export type SubjectSummarySort = 'newest' | 'name' | 'schema' | 'page' | 'edited';

export type SortDirection = 'asc' | 'desc';

export interface SubjectSummaryQuery {
	/** Only this Schema's Subjects; null for every Schema. */
	schema: string | null;
	search: string;
	sort: SubjectSummarySort;
	/** Ignored by the newest sort, which is always newest first. */
	direction: SortDirection;
	/** The previous page's nextCursor; null for the first page. */
	cursor: string | null;
	limit: number;
}

export interface SubjectSummary {
	id: string;
	displayName: string;
	displayNameIsGenerated: boolean;
	schema: string;
	pageId: number;
	pageTitle: string;
	/** ISO 8601, UTC: when the page holding the Subject was last edited. */
	lastEdited: string;
}

export interface SubjectSummaries {
	subjects: SubjectSummary[];
	/** Null once the listing is exhausted. */
	nextCursor: string | null;
}

export interface SubjectSummaryLookup {
	getSubjectSummaries( query: SubjectSummaryQuery ): Promise<SubjectSummaries>;
}
