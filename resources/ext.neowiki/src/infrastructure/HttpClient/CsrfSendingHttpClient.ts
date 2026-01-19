import type { AxiosError, AxiosResponse } from 'axios';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export enum HttpStatus {
	Forbidden = 403
}

export class CsrfSendingHttpClient implements HttpClient {

	public constructor( private readonly httpClient: HttpClient ) {
		this.setCsrfToken();
	}

	public async get( url: string, config?: Record<string, any> ): Promise<Response> {
		const response = await this.httpClient
			.get( url, config )
			.catch( ( err ) => this.handleError( err ) );

		return this.convertResponse( response );
	}

	public async post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.httpClient
			.post( url, data, config )
			.catch( ( err ) => this.handleError( err ) );

		return this.convertResponse( response );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		const response = await this.httpClient
			.patch( url, data, config )
			.catch( ( err ) => this.handleError( err ) );

		return this.convertResponse( response );
	}

	public async delete( url: string, config?: Record<string, any> ): Promise<Response> {
		const response = await this.httpClient
			.delete( url, config )
			.catch( ( err ) => this.handleError( err ) );

		return this.convertResponse( response );
	}

	private convertResponse( response: AxiosResponse|Response ): Response {
		if ( response instanceof Response ) {
			return response;
		}

		return new Response( JSON.stringify( response.data ), {
			status: response.status,
			statusText: response.statusText,
		} );
	}

	private setCsrfToken(): void {
		this.httpClient.getAxiosInstance().defaults.headers.common[ 'X-CSRF-TOKEN' ] = mw.user.tokens.get( 'csrfToken' );
	}

	private async handleError( error: AxiosError ): Promise<any> {
		if (
			error.response?.status === HttpStatus.Forbidden &&
			error.config && error.config.url
		) {
			const isUpdated = await this.updateCsrfToken();
			if ( isUpdated ) {
				// resend with updated token
				error.config.headers[ 'X-CSRF-TOKEN' ] = mw.user.tokens.get( 'csrfToken' );
				return this.httpClient.getAxiosInstance().request( error.config );
			}
		}
		throw error;
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
