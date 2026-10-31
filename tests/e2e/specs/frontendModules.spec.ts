import type { APIRequestContext, Page } from '@playwright/test';
import { test, expect } from '../fixtures';
import { createSchema, createSubjectPage, uniqueName } from '../wikiApi';

const LOAD_LATE = 'function(implementation){setTimeout(function(){mw.loader.impl(implementation)},1500)}';

test( 'a value renders through its property type when the module registering that type loads late', async ( { page, request } ) => {
	await expectColorValueToRender( page, request, { 'ext.redherb': LOAD_LATE } );
} );

test( 'a value renders through its property type when another frontend module fails to load', async ( { page, request } ) => {
	await expectColorValueToRender( page, request, {
		'ext.redherb-create-subject': 'function(){mw.loader.state({"ext.redherb-create-subject":"error"})}',
		'ext.redherb': LOAD_LATE
	} );
} );

async function expectColorValueToRender(
	page: Page,
	request: APIRequestContext,
	moduleHandlers: Record<string, string>
): Promise<void> {
	const pageTitle = uniqueName( 'The Starry Night' );
	await createColorSubjectPage( request, pageTitle );
	const replacedModules = await replaceModules( page, moduleHandlers );

	await page.goto( `index.php?title=${ encodeURIComponent( pageTitle ) }` );

	await expect( page.locator( '.ext-redherb-color-display' ) ).toContainText( '#ff5733' );
	expect( replacedModules ).toEqual( new Set( Object.keys( moduleHandlers ) ) );
}

async function createColorSubjectPage( request: APIRequestContext, pageTitle: string ): Promise<void> {
	const schemaName = uniqueName( 'Painting' );

	await createSchema( request, schemaName, { Accent: { type: 'color' } } );
	await createSubjectPage( request, {
		pageTitle,
		label: 'The Starry Night',
		schema: schemaName,
		statements: {
			Accent: { propertyType: 'color', value: [ '#ff5733' ] }
		}
	} );
}

/**
 * ResourceLoader delivers these modules in a batch with others, so this rewrites the batch to change what
 * happens to one of them. Each handler receives what mw.loader.impl would have.
 */
async function replaceModules( page: Page, handlers: Record<string, string> ): Promise<Set<string>> {
	const replaced = new Set<string>();

	await page.route( /load\.php.*redherb/, async ( route ) => {
		const response = await route.fetch();
		let body = await response.text();

		for ( const [ moduleName, handler ] of Object.entries( handlers ) ) {
			const implementation = `function(){return["${ moduleName }@`;

			if ( body.includes( `mw.loader.impl(${ implementation }` ) ) {
				replaced.add( moduleName );
				body = body.replace( `mw.loader.impl(${ implementation }`, `(${ handler })(${ implementation }` );
			}
		}

		await route.fulfill( { response, body } );
	} );

	return replaced;
}
