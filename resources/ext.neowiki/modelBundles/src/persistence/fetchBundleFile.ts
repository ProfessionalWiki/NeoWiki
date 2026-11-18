import { isHttpUrl } from 'ext.neowiki';

/**
 * A server that does not let other websites read the file fails like a network error does, so the message for
 * both points to downloading the file instead.
 */
export async function fetchBundleFile( url: string ): Promise<string> {
	if ( !isHttpUrl( url ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-url-not-web' ) );
	}

	let response: Response;

	try {
		response = await fetch( url, { cache: 'no-cache' } );
	} catch ( _error ) {
		throw new Error( mw.msg( 'neowiki-bundle-url-unreachable' ) );
	}

	if ( !response.ok ) {
		throw new Error( mw.msg( 'neowiki-bundle-url-failed', String( response.status ) ) );
	}

	return response.text();
}
