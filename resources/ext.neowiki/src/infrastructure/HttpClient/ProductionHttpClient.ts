import axios, { Axios, AxiosHeaders } from 'axios';
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
		return this.send( { ...config, method: 'get', url } );
	}

	public async post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.send( { ...config, method: 'post', url, data } );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.send( { ...config, method: 'patch', url, data } );
	}

	public async put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.send( { ...config, method: 'put', url, data } );
	}

	public async delete( url: string, config?: Record<string, any> ): Promise<Response> {
		return this.send( { ...config, method: 'delete', url } );
	}

	private async send( config: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.request( withJsonBody( config ) );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public getAxiosInstance(): Axios {
		return this.axiosInstance;
	}
}

// axios copies a plain-object body key by key, recursing into nested objects but not arrays, and skips the
// keys __proto__, constructor and prototype, which can be Property names. It sends a string body unchanged,
// but does not label it JSON.
function withJsonBody( config: Record<string, any> ): Record<string, any> {
	if ( isPlainObject( config.data ) && asksForJson( config.headers ) ) {
		return {
			...config,
			data: JSON.stringify( config.data ),
			headers: { 'Content-Type': 'application/json', ...config.headers },
		};
	}

	return config;
}

// The objects axios copies: those with no prototype, and those whose prototype is the Object.prototype of any
// realm, such as an iframe's, which has none itself.
function isPlainObject( value: unknown ): boolean {
	if ( typeof value !== 'object' || value === null ) {
		return false;
	}

	const prototype = Object.getPrototypeOf( value );
	return prototype === null || Object.getPrototypeOf( prototype ) === null;
}

function asksForJson( headers: unknown ): boolean {
	const contentType = AxiosHeaders.from( headers as AxiosHeaders ).getContentType();
	return !contentType || String( contentType ).includes( 'json' );
}
