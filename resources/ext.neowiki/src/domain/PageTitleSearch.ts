export interface PageTitleResult {
	pageId: number;
	title: string;
}

export interface PageTitleSearch {

	searchPageTitles( search: string, limit: number ): Promise<PageTitleResult[]>;

}
