import type { APIRequestContext, Page } from '@playwright/test';
import { test, expect } from '../fixtures';
import { createSchema, createSubjectPage, uniqueName } from '../wikiApi';

type MediaWikiGlobal = {
	config: { get: ( name: string ) => string[] | null };
	loader: { getState: ( moduleName: string ) => string | null };
};

test( 'a Subject shows on its page when the page content arrives after NeoWiki\'s scripts have run', async ( { page, request } ) => {
	const pageTitle = uniqueName( 'Acme' );
	await createMottoSubjectPage( request, pageTitle );
	await holdBackContentUntilNeoWikiScriptsRan( page, pageTitle );

	await page.goto( `index.php?title=${ encodeURIComponent( pageTitle ) }` );

	await expect( page.locator( '.ext-neowiki-infobox__value' ) ).toHaveText( 'Make things' );
	await expect( page.locator( 'script[src="content-gate.js"]' ) ).toBeAttached();
} );

async function createMottoSubjectPage( request: APIRequestContext, pageTitle: string ): Promise<void> {
	const schemaName = uniqueName( 'Company' );

	await createSchema( request, schemaName, { Motto: { type: 'text' } } );
	await createSubjectPage( request, {
		pageTitle,
		label: 'Acme',
		schema: schemaName,
		statements: {
			Motto: { propertyType: 'text', value: [ 'Make things' ] }
		}
	} );
}

/**
 * Stops the browser parsing the page just before its content, as when that part of the HTML is still on a slow
 * connection, while scripts loaded so far keep running.
 */
async function holdBackContentUntilNeoWikiScriptsRan( page: Page, pageTitle: string ): Promise<void> {
	await page.route( ( url ) => url.searchParams.get( 'title' ) === pageTitle, async ( route ) => {
		const response = await route.fetch();
		const body = ( await response.text() )
			.replace( '<div id="mw-content-text"', '<script src="content-gate.js"></script>$&' );

		await route.fulfill( { response, body } );
	} );

	await page.route( '**/content-gate.js', async ( route ) => {
		await waitForNeoWikiScripts( page );
		await route.fulfill( { contentType: 'text/javascript', body: '' } );
	} );
}

/**
 * NeoWiki's UI waits for its own module and the frontend modules of other extensions.
 */
async function waitForNeoWikiScripts( page: Page ): Promise<void> {
	await page.waitForFunction( () => {
		const mw = ( window as unknown as { mw?: MediaWikiGlobal } ).mw;

		if ( mw === undefined ) {
			return false;
		}

		return [ 'ext.neowiki', ...( mw.config.get( 'wgNeoWikiFrontendModules' ) ?? [] ) ]
			.every( ( moduleName ) => mw.loader.getState( moduleName ) === 'ready' );
	} );
}
