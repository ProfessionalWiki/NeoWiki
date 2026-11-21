/**
 * Resolves with the response the server sends, whatever its status, and rejects only when none arrives. Sends a
 * request body as JSON.
 */
export interface HttpClient {
	get( url: string, config?: Record<string, any> ): Promise<Response>;
	post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response>;
	patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response>;
	put( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response>;
	delete( url: string, config?: Record<string, any> ): Promise<Response>;
}
