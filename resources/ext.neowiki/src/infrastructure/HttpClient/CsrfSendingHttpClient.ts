import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export enum HttpStatus {
	Forbidden = 403
}

export class CsrfSendingHttpClient implements HttpClient {

	public constructor( private readonly httpClient: HttpClient ) {
	}

	public async get( url: string, config?: Record<string, any> ): Promise<Response> {
		return this.resendingOnceForbidden( () => this.httpClient.get( url, withCsrfToken( config ) ) );
	}

	public async post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.resendingOnceForbidden( () => this.httpClient.post( url, data, withCsrfToken( config ) ) );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.resendingOnceForbidden( () => this.httpClient.patch( url, data, withCsrfToken( config ) ) );
	}

	public async put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.resendingOnceForbidden( () => this.httpClient.put( url, data, withCsrfToken( config ) ) );
	}

	public async delete( url: string, config?: Record<string, any> ): Promise<Response> {
		return this.resendingOnceForbidden( () => this.httpClient.delete( url, withCsrfToken( config ) ) );
	}

	private async resendingOnceForbidden( send: () => Promise<Response> ): Promise<Response> {
		const response = await send();

		if ( response.status === HttpStatus.Forbidden && await this.updateCsrfToken().catch( () => false ) ) {
			return send();
		}

		return response;
	}

	private async updateCsrfToken(): Promise<boolean> {
		const response = await new mw.Api().get( {
			action: 'query',
			meta: 'tokens',
			format: 'json',
		} );
		const newToken = response?.query.tokens.csrftoken;

		if ( newToken && newToken.length > 0 ) {
			mw.user.tokens.set( 'csrfToken', newToken );
			return true;
		}
		return false;
	}
}

// Read on every send, so a resend carries the token fetched after the 403.
function withCsrfToken( config?: Record<string, any> ): Record<string, any> {
	const headers = new Headers( config?.headers );
	headers.set( 'X-CSRF-TOKEN', mw.user.tokens.get( 'csrfToken' ) );

	return { ...config, headers };
}
