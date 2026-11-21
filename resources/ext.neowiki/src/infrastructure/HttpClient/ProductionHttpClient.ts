import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class ProductionHttpClient implements HttpClient {

	public async get( url: string, config?: Record<string, any> ): Promise<Response> {
		return this.send( 'GET', url, undefined, config );
	}

	public async post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.send( 'POST', url, data, config );
	}

	public async patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.send( 'PATCH', url, data, config );
	}

	public async put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response> {
		return this.send( 'PUT', url, data, config );
	}

	public async delete( url: string, config?: Record<string, any> ): Promise<Response> {
		return this.send( 'DELETE', url, config?.data, config );
	}

	private async send( method: string, url: string, data: unknown, config?: Record<string, any> ): Promise<Response> {
		const headers = new Headers( config?.headers );

		if ( data !== undefined && !headers.has( 'Content-Type' ) ) {
			headers.set( 'Content-Type', 'application/json' );
		}

		return fetch( withoutCredentials( url ), {
			method,
			headers,
			body: data === undefined ? undefined : JSON.stringify( data ),
		} );
	}

}

/**
 * A browser keeps the credentials of a page address such as https://user:pass@wiki.example/ in the base a relative
 * URL resolves against, and fetch refuses a URL that carries them.
 */
function withoutCredentials( url: string ): string {
	const resolved = new URL( url, document.baseURI );
	resolved.username = '';
	resolved.password = '';

	return resolved.href;
}
