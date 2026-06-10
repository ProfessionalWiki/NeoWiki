import axios, { Axios } from 'axios';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class ProductionHttpClient implements HttpClient {
	private readonly axiosInstance: any;

	public constructor() {
		// Accept 422 alongside 2xx so the persistence layer can read structured
		// validation-failed bodies (the default axios behaviour throws on non-2xx
		// with an opaque "Request failed with status code N"). Other non-2xx
		// statuses keep the default rejection so CsrfSendingHttpClient's .catch()
		// based 403 refresh-and-retry path still fires.
		this.axiosInstance = axios.create( {
			validateStatus: ( status: number ) =>
				status === 422 || ( status >= 200 && status < 300 ),
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
		const response = await this.axiosInstance.post( url, data, config );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.patch( url, data, config );
		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	public async put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.axiosInstance.put( url, data, config );
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
