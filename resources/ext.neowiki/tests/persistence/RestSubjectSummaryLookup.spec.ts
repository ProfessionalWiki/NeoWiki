import { describe, expect, it } from 'vitest';
import { RestSubjectSummaryLookup } from '@/persistence/RestSubjectSummaryLookup';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import type { SubjectSummaries, SubjectSummaryQuery } from '@/application/SubjectSummaryLookup';

const REST_URL = '/rest.php';
const LIST_URL = `${ REST_URL }/neowiki/v0/subjects?search=&sort=newest&direction=desc&limit=10`;

const FIRST_PAGE: SubjectSummaryQuery = {
	schema: null, search: '', sort: 'newest', direction: 'desc', cursor: null, limit: 10,
};

const RESULT: SubjectSummaries = {
	subjects: [ {
		id: 's1demo1aaaaaaa1', displayName: 'ZX Spectrum 48K', displayNameIsGenerated: false, schema: 'Computer',
		pageId: 7, pageTitle: 'Sinclair ZX Spectrum', lastEdited: '2026-10-01T14:02:00Z',
	} ],
	nextCursor: 'abc',
};

function jsonResponse( body: unknown ): Response {
	return { ok: true, json: async () => body } as Response;
}

function newLookup( responses: Record<string, Response> ): RestSubjectSummaryLookup {
	return new RestSubjectSummaryLookup( REST_URL, new InMemoryHttpClient( responses ) );
}

describe( 'RestSubjectSummaryLookup', () => {

	// The client answers no other URL, so an empty schema= or cursor= would throw rather than pass.
	it( 'asks for the first page of every Schema without a schema or cursor parameter', async () => {
		const lookup = newLookup( { [ LIST_URL ]: jsonResponse( RESULT ) } );

		expect( await lookup.getSubjectSummaries( FIRST_PAGE ) ).toEqual( RESULT );
	} );

	it( 'passes the Schema, search, order and cursor it is given', async () => {
		const lookup = newLookup( {
			[ `${ REST_URL }/neowiki/v0/subjects?search=zx&sort=name&direction=asc&limit=20&schema=Computer&cursor=abc` ]:
				jsonResponse( RESULT ),
		} );

		expect( await lookup.getSubjectSummaries( {
			schema: 'Computer', search: 'zx', sort: 'name', direction: 'asc', cursor: 'abc', limit: 20,
		} ) ).toEqual( RESULT );
	} );

	it( 'throws when the endpoint fails', async () => {
		const lookup = newLookup( { [ LIST_URL ]: { ok: false, json: async () => RESULT } as Response } );

		await expect( lookup.getSubjectSummaries( FIRST_PAGE ) ).rejects.toThrow( 'Error fetching subject summaries' );
	} );

} );
