import { describe, expect, it } from 'vitest';
import { RestSubjectLabelSearch } from '@/persistence/RestSubjectLabelSearch';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';

const REST_URL = '/rest.php';
const SEARCH_URL = `${ REST_URL }/neowiki/v0/subject-labels?search=acme`;

function newSearch( responses: Record<string, Response> ): RestSubjectLabelSearch {
	return new RestSubjectLabelSearch( REST_URL, new InMemoryHttpClient( responses ) );
}

function jsonResponse( body: unknown ): Response {
	return { ok: true, json: async () => body } as Response;
}

describe( 'RestSubjectLabelSearch', () => {

	it( 'asks for the Subjects of the Schema it is given', async () => {
		const search = newSearch( {
			[ `${ SEARCH_URL }&schema=Company` ]: jsonResponse( [
				{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
			] ),
		} );

		expect( await search.searchSubjectLabels( 'acme', 'Company' ) ).toEqual( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
		] );
	} );

	// The client this search is given answers no other URL, so a request carrying an empty
	// `schema=` would throw rather than pass.
	it( 'names no Schema when it is given none', async () => {
		const search = newSearch( {
			[ SEARCH_URL ]: jsonResponse( [ { id: 's1demo1aaaaaaa1', label: 'ACME Inc.' } ] ),
		} );

		expect( await search.searchSubjectLabels( 'acme' ) ).toEqual( [
			{ id: 's1demo1aaaaaaa1', label: 'ACME Inc.' },
		] );
	} );

	it( 'throws when the search fails', async () => {
		const search = newSearch( {
			[ SEARCH_URL ]: { ok: false, json: async () => ( {} ) } as Response,
		} );

		await expect( search.searchSubjectLabels( 'acme' ) )
			.rejects.toThrow( 'Error searching subject labels' );
	} );

} );
