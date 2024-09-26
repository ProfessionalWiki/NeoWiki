import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class StubHttpClient implements HttpClient {
	private readonly response: Response;

	public constructor( response: Response ) {
		this.response = response;
	}

	public async get( _url: string, _config?: Record<string, any> ): Promise<Response> {
		return this.response;
	}

	public async post( _url: string, _data?: Record<string, any>, _config?: Record<string, any> ): Promise<Response> {
		return this.response;
	}

	public async patch( _url: string, _data?: Record<string, any>, _config?: Record<string, any> ): Promise<Response> {
		return this.response;
	}

	public async delete( _url: string, _config?: Record<string, any> ): Promise<Response> {
		return this.response;
	}
}
