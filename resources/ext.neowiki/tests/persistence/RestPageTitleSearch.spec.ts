import { describe, expect, it } from 'vitest';
import { RestPageTitleSearch } from '@/persistence/RestPageTitleSearch';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';

const REST_URL = '/rest.php';
const SEARCH_URL = `${ REST_URL }/v1/search/title?q=amster&limit=10`;

function newSearch( responses: Record<string, Response> ): RestPageTitleSearch {
	return new RestPageTitleSearch( REST_URL, new InMemoryHttpClient( responses ) );
}

function jsonResponse( body: unknown ): Response {
	return { ok: true, json: async () => body } as Response;
}

describe( 'RestPageTitleSearch', () => {

	it( 'reads MediaWiki\'s own title search, which already carries the page id', async () => {
		const search = newSearch( {
			[ SEARCH_URL ]: jsonResponse( {
				pages: [
					{ id: 12, key: 'Amsterdam_Museum', title: 'Amsterdam Museum' },
					{ id: 34, key: 'Amsterdam', title: 'Amsterdam' },
				],
			} ),
		} );

		expect( await search.searchPageTitles( 'amster', 10 ) ).toEqual( [
			{ pageId: 12, title: 'Amsterdam Museum' },
			{ pageId: 34, title: 'Amsterdam' },
		] );
	} );

	it( 'answers with nothing when the search matched nothing', async () => {
		const search = newSearch( { [ SEARCH_URL ]: jsonResponse( { pages: [] } ) } );

		expect( await search.searchPageTitles( 'amster', 10 ) ).toEqual( [] );
	} );

	it( 'throws when the search fails', async () => {
		const search = newSearch( {
			[ SEARCH_URL ]: { ok: false, json: async () => ( {} ) } as Response,
		} );

		await expect( search.searchPageTitles( 'amster', 10 ) )
			.rejects.toThrow( 'Error searching page titles' );
	} );

} );
