import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';

/**
 * axios leaves applying validateStatus to the adapter, so this stand-in for the network applies the client's own,
 * and rejects as axios does when it says no.
 */
export function productionClientAnswering( status: number, body: unknown ): ProductionHttpClient {
	const httpClient = new ProductionHttpClient();

	( httpClient.getAxiosInstance() as any ).defaults.adapter = ( config: any ): Promise<unknown> => {
		const response = { data: body, status, statusText: '', headers: {}, config };

		if ( config.validateStatus( status ) ) {
			return Promise.resolve( response );
		}

		return Promise.reject( Object.assign(
			new Error( `Request failed with status code ${ status }` ),
			{ response, config, isAxiosError: true },
		) );
	};

	return httpClient;
}
