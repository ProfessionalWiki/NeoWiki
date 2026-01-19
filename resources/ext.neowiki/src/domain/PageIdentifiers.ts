export class PageIdentifiers {

	public constructor(
		private readonly pageId: number,
		private readonly pageTitle: string,
	) {
	}

	public getPageId(): number {
		return this.pageId;
	}

	public getPageName(): string {
		return this.pageTitle;
	}

}
