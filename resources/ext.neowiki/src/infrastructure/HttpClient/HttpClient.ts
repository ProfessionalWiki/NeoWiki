export interface HttpClient {
	get( url: string, config?: Record<string, any> ): Promise<Response>;
	post( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response>;
	patch( url: string, data?: Record<string, any>, config?: Record<string, any> ): Promise<Response>;
	delete( url: string, config?: Record<string, any> ): Promise<Response>;
	[key: string]: any;
}
