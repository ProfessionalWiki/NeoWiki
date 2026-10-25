import axios, { Axios } from 'axios';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class ProductionHttpClient implements HttpClient {
	private readonly axiosInstance: any;

	public constructor() {
		// Accept 422 and 409 alongside 2xx so the persistence layer can read the structured bodies
		// that say what failed: which constraints a Subject violates, and which page or Subject is
		// in the way. The default axios behaviour throws on non-2xx with an opaque "Request failed
		// with status code N" that carries none of it. Other non-2xx statuses keep the default
		// rejection so CsrfSendingHttpClient's .catch() based 403 refresh-and-retry path still fires.
		this.axiosInstance = axios.create( {
			validateStatus: ( status: number ) =>
				status === 422 || status === 409 || ( status >= 200 && status < 300 ),
			// axios labels the string bodies of jsonBody() as form data unless told otherwise.
			headers: {
				post: { 'Content-Type': 'application/json' },
				put: { 'Content-Type': 'application/json' },
				patch: { 'Content-Type': 'application/json' },
			},
		} );
	}

	public async get( url: string, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.get( url, config );
		// Convert Axios response to fetch-like Response
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.post( url, jsonBody( data ), config );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.patch( url, jsonBody( data ), config );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.put( url, jsonBody( data ), config );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async delete( url: string, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.delete( url, config );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public getAxiosInstance(): Axios {
		return this.axiosInstance;
	}
}

// axios copies an object body key by key, at every depth, and the copy skips the keys __proto__,
// constructor and prototype, which can be Property names. A string body is sent as it is.
function jsonBody( data?: Record<string, any> ): string | undefined {
	return data === undefined ? undefined : JSON.stringify( data );
}
