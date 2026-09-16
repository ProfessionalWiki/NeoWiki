export interface PageSaver {
	savePage( pageName: string, source: string, comment: string, content_model: string ): Promise<PageSaverStatus>;
}

/**
 * A refusal always says why, so a caller never has to render the absence of a reason.
 */
export type PageSaverStatus =
	| { success: true }
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
