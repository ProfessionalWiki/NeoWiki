export class PageIdentifiers {

	public constructor(
		private readonly pageId: number,
		private readonly pageTitle: string,
	) {
	}

	/**
	 * The page a Subject bound for one that does not exist yet carries: MediaWiki numbers a page
	 * that is not there 0, and only the write creating it reports its title.
	 */
	public static notYetCreated(): PageIdentifiers {
		return new PageIdentifiers( 0, '' );
	}

	public getPageId(): number {
		return this.pageId;
	}

	public getPageName(): string {
		return this.pageTitle;
	}

}
