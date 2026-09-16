import { PageSaver, PageSaverStatus } from '@/persistence/PageSaver.ts';

/**
 * What mw.Rest hands a failure callback: the jQuery wrapper, whose `exception` is the HTTP
 * reason, around MediaWiki's REST error body.
 */
interface RestFailure {
	readonly exception?: string;
	readonly xhr?: {
		readonly responseJSON?: {
			readonly errorKey?: string;
			readonly messageTranslations?: Record<string, string>;
		};
	};
}

export class MediaWikiPageSaver implements PageSaver {

	private readonly api: mw.Api;

	private readonly rest: mw.Rest;

	private readonly userLanguage: string;

	public constructor( mediawiki: typeof mw ) {
		this.api = new mediawiki.Api();
		this.rest = new mediawiki.Rest();
		this.userLanguage = mediawiki.config.get( 'wgUserLanguage' );
	}

	public async savePage( pageName: string, source: string, comment: string, content_model: string ): Promise<PageSaverStatus> {
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

		// A refused save is an outcome callers act on, not an error the caller cannot see: the
		// status is what PageSaver promises them, so a failure resolves like a success does.
		return new Promise<PageSaverStatus>( ( resolve ) => {
			this.rest.put(
				`/v1/page/${ pageName }`,
				data,
			)
				.done( ( _response ) => {
					resolve( {
						success: true,
					} );
				} )
				.fail( ( _error, failure: RestFailure ) => {
					resolve( {
						success: false,
						message: this.failureMessage( failure ),
					} );
				} );
		} );
	}

	/**
	 * The most specific reason the response carries. MediaWiki translates a REST error into the
	 * languages the request asked for, so the reader's own comes first; an error carrying no
	 * message at all leaves only its key, and a response carrying no body at all the HTTP reason.
	 */
	private failureMessage( failure: RestFailure ): string | undefined {
		const error = failure.xhr?.responseJSON;
		const translations = error?.messageTranslations ?? {};

		return translations[ this.userLanguage ] ??
			Object.values( translations )[ 0 ] ??
			error?.errorKey ??
			failure.exception;
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
