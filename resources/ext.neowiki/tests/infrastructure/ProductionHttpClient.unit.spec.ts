import { describe, expect, it } from 'vitest';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { productionClientAnswering } from './productionClientAnswering.ts';

/**
 * Which statuses come back as a Response the persistence layer can read, and which reject. Axios
 * rejects with an opaque "Request failed with status code N" that carries none of the body, so a
 * status whose body the caller has to read has to be accepted here.
 */
describe( 'ProductionHttpClient', () => {

	function accepts( status: number ): boolean {
		const validateStatus = ( new ProductionHttpClient().getAxiosInstance() as unknown as {
			defaults: { validateStatus: ( status: number ) => boolean };
		} ).defaults.validateStatus;

		return validateStatus( status );
	}

	it( 'accepts success', () => {
		expect( accepts( 200 ) ).toBe( true );
		expect( accepts( 201 ) ).toBe( true );
	} );

	it( 'accepts a validation failure, whose violations the caller reports', () => {
		expect( accepts( 422 ) ).toBe( true );
	} );

	it( 'accepts a conflict, whose body says what is in the way', () => {
		expect( accepts( 409 ) ).toBe( true );
	} );

	// CsrfSendingHttpClient refreshes the token and retries from its .catch(), so a 403 has to
	// reject for that path to fire at all.
	it( 'rejects a refusal, which the CSRF retry is waiting for', () => {
		expect( accepts( 403 ) ).toBe( false );
	} );

	it( 'rejects anything else', () => {
		expect( accepts( 404 ) ).toBe( false );
		expect( accepts( 500 ) ).toBe( false );
	} );

} );

describe( 'ProductionHttpClient request bodies', () => {

	const url = '/rest.php/neowiki';

	const bodySenders = {
		post: ( client: ProductionHttpClient, body: any ): Promise<Response> => client.post( url, body ),
		put: ( client: ProductionHttpClient, body: any ): Promise<Response> => client.put( url, body ),
		patch: ( client: ProductionHttpClient, body: any ): Promise<Response> => client.patch( url, body ),
		delete: ( client: ProductionHttpClient, body: any ): Promise<Response> => client.delete( url, { data: body } ),
	};

	it.each( Object.entries( bodySenders ) )( 'sends fields named after Object members in a %s body', async ( _, send ) => {
		const body = { statements: { constructor: 'a', prototype: 'b', [ '__proto__' ]: 'c' } };

		const request = await requestSentBy( send, body );

		expect( JSON.parse( request.body ) ).toEqual( body );
	} );

	it.each( Object.entries( bodySenders ) )( 'labels a %s body as JSON', async ( _, send ) => {
		const request = await requestSentBy( send, { label: 'Berlin' } );

		expect( request.contentType ).toBe( 'application/json' );
	} );

	it( 'sends a form body as form data', async () => {
		const request = await requestSentBy( bodySenders.post, new URLSearchParams( 'label=Berlin' ) );

		expect( request.body ).toBe( 'label=Berlin' );
		expect( request.contentType ).toMatch( /^application\/x-www-form-urlencoded/ );
	} );

	it( 'labels form data sent straight through its axios instance as form data', async () => {
		const request = await requestSentBy(
			( client, body ) => client.getAxiosInstance().post( url, body ),
			new URLSearchParams( 'label=Berlin' ),
		);

		expect( request.contentType ).toMatch( /^application\/x-www-form-urlencoded/ );
	} );

} );

async function requestSentBy(
	send: ( client: ProductionHttpClient, body: any ) => Promise<unknown>,
	body: unknown,
): Promise<{ body: string; contentType: string }> {
	let sent: any;
	const client = productionClientAnswering( 200, {}, ( config ) => {
		sent = config;
	} );

	await send( client, body );

	return { body: sent.data, contentType: sent.headers.getContentType() };
}
