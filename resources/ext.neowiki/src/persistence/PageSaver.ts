export interface PageSaver {
	savePage( pageName: string, source: string, comment: string, content_model: string ): Promise<PageSaverStatus>;
}

/**
 * A refusal always says why, so a caller never has to render the absence of a reason.
 */
export type PageSaverStatus =
	| { success: true }
	| { success: false; message: string };

/**
 * Saves a page over `baseRevisionId`, the revision the source was edited from; null creates the page.
 * A change made since then is merged into the save where MediaWiki can merge it, and makes the save
 * an edit conflict where it cannot, so it is never overwritten.
 */
export interface PageRevisionSaver {
	saveRevision(
		pageName: string,
		source: string,
		comment: string,
		contentModel: string,
		baseRevisionId: number | null
	): Promise<RevisionSaveStatus>;
}

/**
 * On success, `source` is the page as saved, which holds any change merged into the save.
 */
export type RevisionSaveStatus =
	| { success: true; revisionId: number; source: string }
	| { success: false; message: string };

export class SucceedingPageSaver implements PageSaver {
	public async savePage( _pageName: string, _source: string, _comment: string, _content_model: string ): Promise<PageSaverStatus> {
		return {
			success: true,
		};
	}
}

export class FailingPageSaver implements PageSaver {
	public async savePage( _pageName: string, _source: string, _comment: string, _content_model: string ): Promise<PageSaverStatus> {
		return {
			success: false,
			message: 'Some reason',
		};
	}
}
