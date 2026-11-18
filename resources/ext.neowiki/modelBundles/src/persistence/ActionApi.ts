type ActionApiParameters = Record<string, string | number | boolean | undefined>;

export interface ActionApi {
	get( parameters: ActionApiParameters ): PromiseLike<unknown>;
	/** For a read whose parameters could make a URL longer than web servers take. */
	post( parameters: ActionApiParameters ): PromiseLike<unknown>;
	postWithEditToken( parameters: ActionApiParameters ): PromiseLike<unknown>;
}

/**
 * mw.Api rejects with an error code and the response, so the reason a request failed is read from the
 * second argument, which awaiting the promise would drop.
 */
export function actionApiCall<T>( request: PromiseLike<unknown> ): Promise<T> {
	return new Promise<T>( ( resolve, reject ) => {
		request.then(
			( result ) => resolve( result as T ),
			( code: unknown, result?: { error?: { info?: string } } ) => reject(
				new Error( result?.error?.info ?? ( typeof code === 'string' ? code : String( code ) ) ),
			),
		);
	} );
}
