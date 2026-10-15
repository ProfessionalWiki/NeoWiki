import { describe, expect, it } from 'vitest';
import { RestSubjectSummaryLookup } from '@/persistence/RestSubjectSummaryLookup';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import type { SubjectSummaries, SubjectSummaryQuery } from '@/application/SubjectSummaryLookup';
import { SubjectSummarySearchTimeoutError } from '@/application/SubjectSummarySearchTimeoutError';
import { productionClientAnswering } from '../infrastructure/productionClientAnswering.ts';

const REST_URL = '/rest.php';
const LIST_URL = `${ REST_URL }/neowiki/v0/subjects?search=&sort=newest&direction=desc&limit=10`;
const RECENTLY_EDITED_URL = `${ REST_URL }/neowiki/v0/recently-edited-subjects?limit=3`;

const FIRST_PAGE: SubjectSummaryQuery = {
	schema: null, search: '', sort: 'newest', direction: 'desc', cursor: null, limit: 10,
};

const ZX_SPECTRUM_JSON = {
	id: 's1demo1aaaaaaa1', displayName: 'ZX Spectrum 48K', displayNameIsGenerated: false, schema: 'Computer',
	pageId: 7, pageTitle: 'Sinclair ZX Spectrum', lastEdited: '2026-10-01T14:02:00Z',
};

const RESULT_JSON = { subjects: [ ZX_SPECTRUM_JSON ], nextCursor: 'abc' };

const RESULT: SubjectSummaries = {
	subjects: [ { ...ZX_SPECTRUM_JSON, hostSubject: null } ],
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
		const lookup = newLookup( { [ LIST_URL ]: jsonResponse( RESULT_JSON ) } );

		expect( await lookup.getSubjectSummaries( FIRST_PAGE ) ).toEqual( RESULT );
	} );

	it( 'passes the Schema, search, order and cursor it is given', async () => {
		const lookup = newLookup( {
			[ `${ REST_URL }/neowiki/v0/subjects?search=zx&sort=name&direction=asc&limit=20&schema=Computer&cursor=abc` ]:
				jsonResponse( RESULT_JSON ),
		} );

		expect( await lookup.getSubjectSummaries( {
			schema: 'Computer', search: 'zx', sort: 'name', direction: 'asc', cursor: 'abc', limit: 20,
		} ) ).toEqual( RESULT );
	} );

	it( 'throws when the endpoint fails', async () => {
		const lookup = newLookup( { [ LIST_URL ]: { ok: false, json: async () => RESULT } as Response } );

		await expect( lookup.getSubjectSummaries( FIRST_PAGE ) ).rejects.toThrow( 'Error fetching subject summaries' );
	} );

	it( 'reads the Host Subjects a Dependent Subject is named after', async () => {
		const birth = {
			...ZX_SPECTRUM_JSON,
			hostSubject: {
				id: 's1demo1aaaaaaa2', schema: 'Birth', displayName: 'Birth', displayNameIsGenerated: true,
				hostSubject: {
					id: 's1demo1aaaaaaa3', schema: { source: 'wikibase', name: 'Person' }, displayName: 'Pablo Picasso',
					displayNameIsGenerated: false,
				},
			},
		};
		const lookup = newLookup( { [ RECENTLY_EDITED_URL ]: jsonResponse( { subjects: [ birth ] } ) } );

		expect( ( await lookup.getRecentlyEditedSubjects( 3 ) )[ 0 ].hostSubject ).toEqual( {
			id: 's1demo1aaaaaaa2', schemaName: 'Birth', displayName: 'Birth', displayNameIsGenerated: true,
			hostSubject: {
				id: 's1demo1aaaaaaa3', schemaName: 'Person', displayName: 'Pablo Picasso', displayNameIsGenerated: false,
				hostSubject: null,
			},
		} );
	} );

	it( 'says the search took too long when the wiki stops it', async () => {
		const lookup = new RestSubjectSummaryLookup( REST_URL, productionClientAnswering( 503, {
			errorType: 'searchTimeout',
			message: 'The search took too long. Search within one Schema to search fewer Subjects.',
			httpCode: 503,
			httpReason: 'Service Unavailable',
		} ) );

		await expect( lookup.getSubjectSummaries( FIRST_PAGE ) ).rejects.toBeInstanceOf( SubjectSummarySearchTimeoutError );
	} );

	it( 'takes no other refusal for a search that took too long', async () => {
		const lookup = new RestSubjectSummaryLookup(
			REST_URL,
			productionClientAnswering( 503, '<html><body>Service Unavailable</body></html>' ),
		);

		await expect( lookup.getSubjectSummaries( FIRST_PAGE ) ).rejects.not.toBeInstanceOf( SubjectSummarySearchTimeoutError );
	} );

	it( 'asks for as many of the Subjects edited last as it is given', async () => {
		const lookup = newLookup( { [ RECENTLY_EDITED_URL ]: jsonResponse( { subjects: RESULT_JSON.subjects } ) } );

		expect( await lookup.getRecentlyEditedSubjects( 3 ) ).toEqual( RESULT.subjects );
	} );

	it( 'throws when the Subjects edited last cannot be fetched', async () => {
		const lookup = newLookup( {
			[ RECENTLY_EDITED_URL ]: { ok: false, json: async () => ( { subjects: RESULT_JSON.subjects } ) } as Response,
		} );

		await expect( lookup.getRecentlyEditedSubjects( 3 ) ).rejects.toThrow();
	} );

} );
