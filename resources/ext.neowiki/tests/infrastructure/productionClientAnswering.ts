import { vi } from 'vitest';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';

/**
 * A ProductionHttpClient whose network answers its requests with these statuses in turn, the last for any
 * further request, each with this body: a string body as it is, any other as JSON.
 */
export function productionClientAnswering(
	status: number | number[],
	body: unknown,
	onRequest?: ( request: Request ) => void,
): ProductionHttpClient {
	const statuses = Array.isArray( status ) ? status : [ status ];
	let requestCount = 0;

	vi.stubGlobal( 'fetch', async ( input: RequestInfo | URL, init?: RequestInit ): Promise<Response> => {
		// Built as a browser's fetch builds it, so the stand-in refuses what a browser refuses.
		const request = new Request( input instanceof Request ? input : new URL( String( input ), document.baseURI ), init );
		onRequest?.( request );
		const answer = statuses[ Math.min( requestCount++, statuses.length - 1 ) ];

		return new Response( typeof body === 'string' ? body : JSON.stringify( body ), { status: answer } );
	} );

	return new ProductionHttpClient();
}
