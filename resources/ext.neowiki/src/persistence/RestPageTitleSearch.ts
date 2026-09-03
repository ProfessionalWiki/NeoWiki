import type { PageTitleSearch, PageTitleResult } from '@/domain/PageTitleSearch';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

/**
 * Page-title completion over MediaWiki's own search route, which carries the page id alongside the
 * title and drops results the viewer may not read. NeoWiki adds no endpoint of its own for this:
 * the one thing core cannot do here is scope the search to particular namespaces, and Subjects are
 * not restricted to any.
 */
export class RestPageTitleSearch implements PageTitleSearch {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async searchPageTitles( search: string, limit: number ): Promise<PageTitleResult[]> {
		const params = new URLSearchParams( { q: search, limit: String( limit ) } );
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/v1/search/title?${ params.toString() }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error searching page titles' );
		}

		const body = await response.json();

		return ( body.pages ?? [] ).map( ( page: { id: number; title: string } ) => ( {
			pageId: page.id,
			title: page.title,
		} ) );
	}

}
