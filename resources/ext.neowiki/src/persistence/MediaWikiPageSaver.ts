import { PageSaver, PageSaverStatus } from '@/persistence/PageSaver.ts';

export class MediaWikiPageSaver implements PageSaver {

	private readonly api: mw.Api;

	private readonly rest: mw.Rest;

	public constructor( mediawiki: typeof mw ) {
		this.api = new mediawiki.Api();
		this.rest = new mediawiki.Rest();
	}

	public async savePage( pageName: string, source: string, comment: string, content_model: string ): Promise<PageSaverStatus> {
		const revisionId = await this.getPageRevision( pageName );

		const data = {
			source: source,
			comment: comment,
			content_model: content_model,
			token: await this.getEditToken()
		};

		if ( revisionId !== undefined ) {
			( data as any ).latest = { id: revisionId };
		}

		return new Promise<PageSaverStatus>( ( resolve, reject ) => {
			this.rest.put(
				`/v1/page/${ pageName }`,
				data
			)
				.done( ( _response ) => {
					resolve( {
						success: true
					} );
				} )
				.fail( ( _error, response ) => {
					reject( {
						success: false,
						// TODO: find a better message in the response.
						message: response.exception
					} );
				} );
		} );
	}

	private async getPageRevision( pageName: string ): Promise<string | undefined> {
		try {
			const page = await this.rest.get( `/v1/page/${ pageName }`, {} );
			return page.latest.id;
		} catch ( _error ) {
			return undefined;
		}
	}

	private async getEditToken(): Promise<string> {
		return this.api.getEditToken();
	}

}
