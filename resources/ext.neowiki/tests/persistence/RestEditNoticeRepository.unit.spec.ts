import { describe, expect, it } from 'vitest';
import { RestEditNoticeRepository } from '@/persistence/RestEditNoticeRepository';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

const API_URL = 'https://example.com/rest.php';
const PAGE_ID = 42;

function newRepository( httpClient: HttpClient ): RestEditNoticeRepository {
	return new RestEditNoticeRepository( API_URL, httpClient );
}

function respondingWith( url: string, body: unknown, status = 200 ): InMemoryHttpClient {
	return new InMemoryHttpClient( {
		[ url ]: new Response( JSON.stringify( body ), { status } ),
	} );
}

describe( 'RestEditNoticeRepository', () => {

	it( 'returns the notices the endpoint reports', async () => {
		const httpClient = respondingWith(
			`${ API_URL }/neowiki/v0/page/${ PAGE_ID }/editNotices`,
			{ notices: [
				{ key: 'neowiki-editnotice-0', html: '<p>Namespace</p>' },
				{ key: 'contentstabilization-approvalnotice', html: '<b>Needs review</b>' },
			] },
		);

		const notices = await newRepository( httpClient ).getNotices( PAGE_ID );

		expect( notices ).toStrictEqual( [
			{ key: 'neowiki-editnotice-0', html: '<p>Namespace</p>' },
			{ key: 'contentstabilization-approvalnotice', html: '<b>Needs review</b>' },
		] );
	} );

	it( 'returns nothing when the page has no notices', async () => {
		const httpClient = respondingWith(
			`${ API_URL }/neowiki/v0/page/${ PAGE_ID }/editNotices`,
			{ notices: [] },
		);

		expect( await newRepository( httpClient ).getNotices( PAGE_ID ) ).toStrictEqual( [] );
	} );

	it( 'asks for Schema-scoped notices when the Schema is known', async () => {
		const httpClient = respondingWith(
			`${ API_URL }/neowiki/v0/page/${ PAGE_ID }/editNotices?schema=Control+Document`,
			{ notices: [ { key: 'neowiki-editnotice-schema-Control_Document', html: '<p>Schema</p>' } ] },
		);

		const notices = await newRepository( httpClient ).getNotices( PAGE_ID, 'Control Document' );

		expect( notices ).toStrictEqual( [
			{ key: 'neowiki-editnotice-schema-Control_Document', html: '<p>Schema</p>' },
		] );
	} );

	it( 'shows no notices rather than breaking the editor when the request fails', async () => {
		const httpClient = respondingWith(
			`${ API_URL }/neowiki/v0/page/${ PAGE_ID }/editNotices`,
			{ httpCode: 500 },
			500,
		);

		expect( await newRepository( httpClient ).getNotices( PAGE_ID ) ).toStrictEqual( [] );
	} );

	it( 'shows no notices rather than breaking the editor when the response is malformed', async () => {
		const httpClient = respondingWith(
			`${ API_URL }/neowiki/v0/page/${ PAGE_ID }/editNotices`,
			{ unexpected: true },
		);

		expect( await newRepository( httpClient ).getNotices( PAGE_ID ) ).toStrictEqual( [] );
	} );

} );
