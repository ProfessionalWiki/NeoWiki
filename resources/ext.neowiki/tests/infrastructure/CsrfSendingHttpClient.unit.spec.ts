import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CsrfSendingHttpClient } from '@/infrastructure/HttpClient/CsrfSendingHttpClient';
import { productionClientAnswering } from './productionClientAnswering.ts';

const url = '/rest.php/neowiki/v0/subject/s11111111111111';

let freshToken: string;
let tokenQueryFails: boolean;

beforeEach( () => {
	freshToken = 'token-2';
	tokenQueryFails = false;
	const tokens = new Map( [ [ 'csrfToken', 'token-1' ] ] );

	vi.stubGlobal( 'mw', {
		user: { tokens },
		Api: class {
			public async get(): Promise<unknown> {
				if ( tokenQueryFails ) {
					throw new Error( 'http' );
				}

				return { query: { tokens: { csrftoken: freshToken } } };
			}
		},
	} );
} );

function clientAnswering( ...statuses: number[] ): { client: CsrfSendingHttpClient; sent: Request[] } {
	const sent: Request[] = [];
	const client = new CsrfSendingHttpClient( productionClientAnswering( statuses, {}, ( request ) => {
		sent.push( request );
	} ) );

	return { client, sent };
}

async function described( request: Request ): Promise<{ method: string; body: string; token: string | null }> {
	return { method: request.method, body: await request.text(), token: request.headers.get( 'X-CSRF-TOKEN' ) };
}

describe( 'CsrfSendingHttpClient', () => {

	const body = { label: 'Berlin' };

	const requests: [ string, ( client: CsrfSendingHttpClient ) => Promise<Response>, string ][] = [
		[ 'GET', ( client ) => client.get( url ), '' ],
		[ 'POST', ( client ) => client.post( url, body ), JSON.stringify( body ) ],
		[ 'PUT', ( client ) => client.put( url, body ), JSON.stringify( body ) ],
		[ 'PATCH', ( client ) => client.patch( url, body ), JSON.stringify( body ) ],
		[ 'DELETE', ( client ) => client.delete( url, { data: body } ), JSON.stringify( body ) ],
	];

	it.each( requests )( 'passes a %s request on with the CSRF token', async ( method, send, sentBody ) => {
		const { client, sent } = clientAnswering( 200 );

		await send( client );

		expect( await described( sent[ 0 ] ) ).toEqual( { method, body: sentBody, token: 'token-1' } );
	} );

	it( 'sends the headers the caller gives with a request', async () => {
		const { client, sent } = clientAnswering( 200 );

		await client.get( url, { headers: { 'Cache-Control': 'no-cache' } } );

		expect( sent[ 0 ].headers.get( 'Cache-Control' ) ).toBe( 'no-cache' );
	} );

	it.each( requests )( 'resends a forbidden %s request with a fresh token', async ( method, send, sentBody ) => {
		const { client, sent } = clientAnswering( 403, 200 );

		const response = await send( client );

		expect( response.status ).toBe( 200 );
		expect( await described( sent[ 1 ] ) ).toEqual( { method, body: sentBody, token: 'token-2' } );
	} );

	it( 'resends a forbidden request only once', async () => {
		const { client, sent } = clientAnswering( 403, 403, 200 );

		const response = await client.put( url, {} );

		expect( response.status ).toBe( 403 );
		expect( sent ).toHaveLength( 2 );
	} );

	it( 'hands on the 403 when no fresh token comes', async () => {
		freshToken = '';
		const { client, sent } = clientAnswering( 403, 200 );

		const response = await client.put( url, {} );

		expect( response.status ).toBe( 403 );
		expect( sent ).toHaveLength( 1 );
	} );

	it( 'hands on the 403 when no fresh token can be fetched', async () => {
		tokenQueryFails = true;
		const { client, sent } = clientAnswering( 403, 200 );

		const response = await client.put( url, {} );

		expect( response.status ).toBe( 403 );
		expect( sent ).toHaveLength( 1 );
	} );

	it( 'hands on any other failure without resending', async () => {
		const { client, sent } = clientAnswering( 500, 200 );

		const response = await client.put( url, {} );

		expect( response.status ).toBe( 500 );
		expect( sent ).toHaveLength( 1 );
	} );

} );
