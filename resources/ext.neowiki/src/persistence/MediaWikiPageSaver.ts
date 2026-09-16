import { PageSaver, PageSaverStatus } from '@/persistence/PageSaver.ts';

/**
 * The fields of MediaWiki's REST error body that a failure is reported from.
 */
interface RestErrorBody {
	readonly message?: string;
	readonly messageTranslations?: Record<string, string>;
}

export class MediaWikiPageSaver implements PageSaver {

	private readonly api: mw.Api;

	private readonly rest: mw.Rest;

	public constructor( mediawiki: typeof mw ) {
		this.api = new mediawiki.Api();
		this.rest = new mediawiki.Rest();
	}

	public async savePage( pageName: string, source: string, comment: string, content_model: string ): Promise<PageSaverStatus> {
		try {
			const revisionId = await this.getPageRevision( pageName );

			const data = {
				source: source,
				comment: comment,
				content_model: content_model,
				token: await this.getEditToken(),
			};

			if ( revisionId !== undefined ) {
				( data as any ).latest = { id: revisionId };
			}

			return await this.putPage( pageName, data );
		} catch ( error ) {
			// The token request fails over the network like the save does, and its rejection
			// must not escape the status this method promises.
			return { success: false, message: error instanceof Error ? error.message : String( error ) };
		}
	}

	private putPage( pageName: string, data: object ): Promise<PageSaverStatus> {
		return new Promise<PageSaverStatus>( ( resolve ) => {
			this.rest.put( `/v1/page/${ pageName }`, data )
				.done( () => {
					resolve( { success: true } );
				} )
				.fail( ( _code: string, failure: mw.Rest.HttpErrorData ) => {
					resolve( { success: false, message: MediaWikiPageSaver.failureMessage( failure ) } );
				} );
		} );
	}

	/**
	 * The most specific reason the response carries. A localized REST error comes in the wiki's
	 * content language and then in English, so the first translation is the wiki's own; an error
	 * raised outside that path carries a plain message; a response without a body leaves the HTTP
	 * reason, and a failure below HTTP only jQuery's status. Falsy rather than nullish at each
	 * step, since an empty string is no more use to a reader than a missing one.
	 */
	private static failureMessage( failure: mw.Rest.HttpErrorData ): string {
		const error: RestErrorBody = failure.xhr?.responseJSON ?? {};

		return Object.values( error.messageTranslations ?? {} )[ 0 ] ||
			error.message ||
			failure.exception ||
			failure.textStatus;
	}

	/**
	 * The page's current revision id, or undefined for a page that does not exist yet.
	 * Cache-Control keeps the browser from answering from its cache, which would make the
	 * save carry a stale base revision (#1303).
	 */
	private async getPageRevision( pageName: string ): Promise<number | undefined> {
		try {
			const page = await this.rest.get( `/v1/page/${ pageName }`, {}, { 'Cache-Control': 'no-cache' } );
			return page.latest.id;
		} catch ( _error ) {
			return undefined;
		}
	}

	private async getEditToken(): Promise<string> {
		return this.api.getEditToken();
	}

}
