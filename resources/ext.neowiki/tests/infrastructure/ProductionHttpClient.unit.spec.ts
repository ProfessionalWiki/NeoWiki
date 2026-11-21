import { afterEach, describe, expect, it, vi } from 'vitest';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { productionClientAnswering } from './productionClientAnswering.ts';

describe( 'ProductionHttpClient', () => {

	it.each( [ 200, 201, 403, 404, 409, 422, 500 ] )( 'hands the caller a %i response', async ( status ) => {
		const response = await productionClientAnswering( status, { message: 'The database is locked' } ).get( '/rest.php/neowiki' );

		expect( response.status ).toBe( status );
		expect( await response.json() ).toEqual( { message: 'The database is locked' } );
	} );

	it( 'rejects when no response arrives', async () => {
		vi.stubGlobal( 'fetch', () => Promise.reject( new TypeError( 'Failed to fetch' ) ) );

		await expect( new ProductionHttpClient().get( '/rest.php/neowiki' ) ).rejects.toThrow( 'Failed to fetch' );
	} );

} );

describe( 'ProductionHttpClient requests', () => {

	const url = '/rest.php/neowiki';

	afterEach( () => {
		delete ( document as { baseURI?: string } ).baseURI;
	} );

	const bodySenders: Record<string, BodySender> = {
		post: ( client, body ) => client.post( url, body ),
		put: ( client, body ) => client.put( url, body ),
		patch: ( client, body ) => client.patch( url, body ),
		delete: ( client, body ) => client.delete( url, { data: body } ),
	};

	const senders: Record<string, BodySender> = {
		get: ( client ) => client.get( url ),
		...bodySenders,
	};

	it.each( Object.entries( senders ) )( 'sends a %s request with that method', async ( method, send ) => {
		const request = await requestSentBy( send, { label: 'Berlin' } );

		expect( request.method ).toBe( method.toUpperCase() );
	} );

	it( 'sends a request from a page whose address carries credentials', async () => {
		pageAddressIs( 'https://user:pass@wiki.example/wiki/Berlin' );

		const request = await requestSentBy( senders.get, undefined );

		expect( request.url ).toBe( 'https://wiki.example/rest.php/neowiki' );
	} );

	it( 'sends the headers the caller gives', async () => {
		const request = await requestSentBy(
			( client ) => client.get( url, { headers: { 'Cache-Control': 'no-cache' } } ),
			undefined,
		);

		expect( request.headers.get( 'Cache-Control' ) ).toBe( 'no-cache' );
	} );

	it.each( Object.entries( bodySenders ) )( 'sends a %s body as JSON', async ( _, send ) => {
		const request = await requestSentBy( send, { label: 'Berlin' } );

		expect( JSON.parse( request.body ) ).toEqual( { label: 'Berlin' } );
		expect( request.headers.get( 'Content-Type' ) ).toMatch( /^application\/json/ );
	} );

	it( 'keeps the JSON type the caller labels a body with', async () => {
		const request = await requestSentBy(
			( client, body ) => client.patch( url, body, { headers: { 'Content-Type': 'application/merge-patch+json' } } ),
			{ label: 'Berlin' },
		);

		expect( request.headers.get( 'Content-Type' ) ).toMatch( /^application\/merge-patch\+json/ );
	} );

} );

type BodySender = ( client: ProductionHttpClient, body: any ) => Promise<unknown>;

/**
 * The address relative URLs resolve against: a browser keeps credentials there that it hides from location.href.
 */
function pageAddressIs( address: string ): void {
	Object.defineProperty( document, 'baseURI', { value: address, configurable: true } );
}

async function requestSentBy( send: BodySender, body: unknown ): Promise<{ url: string; method: string; headers: Headers; body: string }> {
	let sent: Request | undefined;
	const client = productionClientAnswering( 200, {}, ( request ) => {
		sent = request;
	} );

	await send( client, body );

	return { url: sent!.url, method: sent!.method, headers: sent!.headers, body: await sent!.text() };
}
