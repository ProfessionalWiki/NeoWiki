import { PageSaver, PageSaverStatus } from '@/persistence/PageSaver.ts';

/**
 * MediaWiki's REST error body. Only the fields a failure is reported from: the jQuery wrapper
 * around it is typed by mw.Rest, but `responseJSON` is `any` there whatever its generic says.
 */
interface RestErrorBody {
	readonly message?: string;
	readonly errorKey?: string;
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
			return await this.putPage( pageName, await this.saveRequest( source, comment, content_model, pageName ) );
		} catch ( error ) {
			// The edit token is fetched over the network too, and fails the ways the save itself
			// does. Its rejection would otherwise escape the status this method promises.
			return { success: false, message: error instanceof Error ? error.message : String( error ) };
		}
	}

	private async saveRequest( source: string, comment: string, content_model: string, pageName: string ): Promise<object> {
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

		return data;
	}

	/**
	 * A refused save is an outcome callers act on, not an error they cannot see: the status is
	 * what PageSaver promises them, so a failure resolves like a success does.
	 */
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
	 * The most specific reason the response carries. MediaWiki answers a REST error in the wiki's
	 * content language and English, keyed by BCP 47, so there is no code here to match a reader
	 * against: the first translation is the wiki's own. An error raised outside that path carries a
	 * plain message instead, one carrying no message at all leaves only its key, and a response
	 * with no body at all the HTTP reason.
	 *
	 * Falsy rather than nullish at each step: an empty string is no more use to a reader than a
	 * missing one, and jQuery reports an aborted or network-level failure as exactly that.
	 */
	private static failureMessage( failure: mw.Rest.HttpErrorData ): string {
		const error: RestErrorBody = failure.xhr?.responseJSON ?? {};

		return Object.values( error.messageTranslations ?? {} )[ 0 ] ||
			error.message ||
			error.errorKey ||
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
