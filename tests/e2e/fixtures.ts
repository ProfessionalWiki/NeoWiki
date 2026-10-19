import { test as base, expect, type Page, type Response } from '@playwright/test';

/**
 * Every test fails if a page it visits throws, logs an error, gets an error response from the wiki,
 * or shows a ⧼key⧽ placeholder for a message missing from its ResourceLoader module.
 */
export const test = base.extend( {
	page: async ( { page }, use ) => {
		const problems: string[] = [];
		await recordProblems( page, problems );

		await use( page );

		expect( problems, 'problems seen while the test ran' ).toEqual( [] );
	}
} );

export { expect };

async function recordProblems( page: Page, problems: string[] ): Promise<void> {
	page.on( 'pageerror', ( error ) => problems.push( `Uncaught error: ${ error.message }` ) );

	page.on( 'console', ( message ) => {
		// The browser's own report of an error response, which the response check below judges.
		if ( message.type() === 'error' && !message.text().startsWith( 'Failed to load resource' ) ) {
			problems.push( `Console error: ${ message.text() }` );
		}
	} );

	page.on( 'response', ( response ) => {
		if ( isUnexpectedErrorResponse( response ) ) {
			problems.push( `HTTP ${ response.status() }: ${ response.request().method() } ${ response.url() }` );
		}
	} );

	await page.exposeFunction( 'reportRawMessageKey', ( text: string ) => {
		problems.push( `Raw message key: ${ text }` );
	} );
	await page.addInitScript( observeRawMessageKeys );
}

function isUnexpectedErrorResponse( response: Response ): boolean {
	if ( response.status() < 400 ) {
		return false;
	}

	// Saving a page first looks it up to learn its latest revision; a page not created yet is a 404.
	return !( response.status() === 404 &&
		response.request().method() === 'GET' &&
		new URL( response.url() ).pathname.includes( '/rest.php/v1/page/' ) );
}

// Runs in the browser, so it cannot use anything from this module.
function observeRawMessageKeys(): void {
	const report = ( text: string | null ): void => {
		const key = text?.match( /⧼[^⧽]*⧽/ );

		if ( key ) {
			( window as unknown as { reportRawMessageKey: ( text: string ) => void } ).reportRawMessageKey( key[ 0 ] );
		}
	};

	new MutationObserver( ( records ) => {
		for ( const record of records ) {
			if ( record.type === 'attributes' ) {
				report( ( record.target as Element ).getAttribute( record.attributeName! ) );
			} else if ( record.type === 'characterData' ) {
				report( record.target.textContent );
			} else {
				// An inserted element can bring its attributes with it, as Vue sets them before inserting.
				record.addedNodes.forEach( ( node ) => report( node instanceof Element ? node.outerHTML : node.textContent ) );
			}
		}
	} ).observe( document, { childList: true, subtree: true, characterData: true, attributes: true } );
}
