import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class InMemoryHttpClient implements HttpClient {
	private readonly responses: Record<string, Response>;

	public constructor( responses: Record<string, Response> ) {
		this.responses = responses;
	}

	public async get( url: string, _config?: Record<string, any> ): Promise<Response> {
		const response = this.responses[ url ];

		if ( !response ) {
			throw new Error( `No response found for URL: ${ url }` );
		}

		return response;
	}

	public async post( url: string, _data?: Record<string, any>, _config?: Record<string, any> ): Promise<Response> {
		const response = this.responses[ url ];

		if ( !response ) {
			throw new Error( `No response found for URL: ${ url }` );
		}

		return response;
	}

	public async patch( url: string, _data?: Record<string, any>, _config?: Record<string, any> ): Promise<Response> {
		const response = this.responses[ url ];

		if ( !response ) {
			throw new Error( `No response found for URL: ${ url }` );
		}

		return response;
	}

	public async delete( url: string, _config?: Record<string, any> ): Promise<Response> {
		const response = this.responses[ url ];

		if ( !response ) {
			throw new Error( `No response found for URL: ${ url }` );
		}

		return response;
	}
}
