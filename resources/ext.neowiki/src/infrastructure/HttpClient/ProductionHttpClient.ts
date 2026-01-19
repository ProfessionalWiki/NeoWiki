import axios, { Axios } from 'axios';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class ProductionHttpClient implements HttpClient {
	private readonly axiosInstance: any;

	public constructor() {
		this.axiosInstance = axios.create();
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
