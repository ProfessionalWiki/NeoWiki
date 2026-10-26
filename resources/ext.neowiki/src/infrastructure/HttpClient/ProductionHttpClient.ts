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
		const response = await this.axiosInstance.post( url, jsonBody( data ), jsonConfig( data, config ) );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.patch( url, jsonBody( data ), jsonConfig( data, config ) );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.put( url, jsonBody( data ), jsonConfig( data, config ) );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async delete( url: string, config?: Record<string, any> ): Promise<Response> {
		const data = config?.data;
		const response = await this.axiosInstance.delete( url, { ...jsonConfig( data, config ), data: jsonBody( data ) } );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public getAxiosInstance(): Axios {
		return this.axiosInstance;
	}
}

// axios copies a plain-object body and the objects nested in it key by key, skipping the keys __proto__,
// constructor and prototype, which can be Property names. It sends a string body as it is, labelled as
// form data unless told otherwise.
function jsonBody( data: unknown ): unknown {
	return isPlainObject( data ) ? JSON.stringify( data ) : data;
}

function jsonConfig( data: unknown, config?: Record<string, any> ): Record<string, any> | undefined {
	if ( isPlainObject( data ) ) {
		return { ...config, headers: { 'Content-Type': 'application/json', ...config?.headers } };
	}

	return config;
}

function isPlainObject( value: unknown ): boolean {
	if ( typeof value !== 'object' || value === null ) {
		return false;
	}

	const prototype = Object.getPrototypeOf( value );
	return prototype === Object.prototype || prototype === null;
}
