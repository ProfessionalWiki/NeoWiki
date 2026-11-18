import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../fixtures';
import { createSchema, pageJson, uniqueName } from '../wikiApi';

test( 'a new Schema saves a Relation property with its target Schema', async ( { page, request } ) => {
	const targetName = uniqueName( 'Target' );
	const schemaName = uniqueName( 'Source' );
	await createSchema( request, targetName, {} );

	await page.goto( 'index.php?title=Special:Schemas' );
	await page.getByRole( 'button', { name: 'Create schema' } ).click();
	const dialog = schemaCreator( page );
	// The Schema's name, in the pane of the Schema row the dialog opens on.
	await dialog.getByRole( 'textbox', { name: 'Name', exact: true } ).fill( schemaName );
	await dialog.getByRole( 'button', { name: 'Add property definition' } ).click();
	await dialog.getByRole( 'textbox', { name: 'Name', exact: true } ).fill( 'Employer' );
	await dialog.getByRole( 'combobox' ).first().click();
	await page.getByRole( 'option', { name: 'Relation' } ).click();
	// The lookup searches as keys are typed; fill() would set the text without a search.
	await dialog.getByRole( 'combobox', { name: 'Target schemas' } ).pressSequentially( targetName );
	await page.getByRole( 'option', { name: targetName, exact: true } ).click();
	await page.keyboard.press( 'Tab' );
	await dialog.getByRole( 'button', { name: 'Create schema' } ).click();
	await expect( dialog ).toBeHidden();

	expect( await pageJson( request, `Schema:${ schemaName }` ) ).toMatchObject( {
		propertyDefinitions: {
			Employer: { type: 'relation', targetSchemas: [ targetName ] }
		}
	} );
} );

test( 'a Schema saves the labels given in the pane of its own row', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Labelled' );
	await createSchema( request, schemaName, {} );

	const dialog = await openSchemaEditor( page, schemaName );
	await dialog.getByRole( 'option', { name: 'Schema', exact: true } ).click();
	await dialog.getByRole( 'textbox', { name: 'Labels text 1' } ).fill( 'Firm' );
	await dialog.getByRole( 'button', { name: 'Save schema' } ).click();
	await expect( dialog ).toBeHidden();

	expect( await pageJson( request, `Schema:${ schemaName }` ) ).toMatchObject( { labels: { en: 'Firm' } } );
} );

// A menu opening past the bottom of the pane it is in escapes the pane's scrolling, as Codex intends for
// menus in a dialog; nothing between it and the dialog may mask it either.
test( 'the property editor shows a menu that runs past the bottom of its pane', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Menus' );
	await createSchema( request, schemaName, { Founded: { type: 'number' } } );
	await page.setViewportSize( { width: 1280, height: 600 } );

	const dialog = await openSchemaEditor( page, schemaName );
	await dialog.getByRole( 'combobox' ).first().click();
	const lastOption = page.getByRole( 'option' ).last();
	await lastOption.scrollIntoViewIfNeeded();

	const placement = await lastOption.evaluate( ( option ) => {
		const pane = option.closest( '.ext-neowiki-editor-panes__pane' ) as Element;
		const box = option.getBoundingClientRect();
		const masking: string[] = [];
		for ( let element = option.parentElement; element !== null && !element.classList.contains( 'cdx-dialog' ); element = element.parentElement ) {
			if ( getComputedStyle( element ).maskImage !== 'none' ) {
				masking.push( element.className );
			}
		}
		return {
			pastPane: box.bottom > pane.getBoundingClientRect().bottom,
			onTop: option.contains( document.elementFromPoint( box.left + box.width / 2, box.top + box.height / 2 ) ),
			masking
		};
	} );

	// What makes the test mean anything: the option lies past the pane's bottom edge.
	expect( placement.pastPane ).toBe( true );
	// Nothing covers it, and nothing masks it, which hit-testing need not see.
	expect( placement.onTop ).toBe( true );
	expect( placement.masking ).toEqual( [] );
} );

test( 'the property editor fades its pane by as much as the content runs past each edge', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Fades' );
	await createSchema( request, schemaName, { Founded: { type: 'number' } } );
	await page.setViewportSize( { width: 1280, height: 500 } );

	const pane = ( await openSchemaEditor( page, schemaName ) ).locator( '.ext-neowiki-editor-panes__pane' ).last();

	await scrollTo( pane, 10 );
	await expect.poll( () => fadeDepths( pane ) ).toEqual( { start: 10, end: 24 } );

	await scrollTo( pane, Infinity );
	await expect.poll( () => fadeDepths( pane ) ).toEqual( { start: 24, end: 0 } );
} );

test( 'the Create schema dialog scrolls its property editor with the wheel', async ( { page } ) => {
	await page.setViewportSize( { width: 1280, height: 600 } );

	await page.goto( 'index.php?title=Special:Schemas' );
	await page.getByRole( 'button', { name: 'Create schema' } ).click();
	const dialog = schemaCreator( page );
	await dialog.getByRole( 'button', { name: 'Add property definition' } ).click();
	const pane = dialog.locator( '.ext-neowiki-editor-panes__pane' ).last();
	const scrollTop = (): Promise<number> => pane.evaluate( ( element ) => element.scrollTop );

	// What makes the test mean anything: the property editor has more to show than fits.
	expect( await pane.evaluate( ( element ) => element.scrollHeight > element.clientHeight ) ).toBe( true );

	await dialog.getByRole( 'textbox', { name: 'Name', exact: true } ).hover();
	const scrollTopBefore = await scrollTop();
	await page.mouse.wheel( 0, 100 );

	await expect.poll( scrollTop ).toBeGreaterThan( scrollTopBefore );
} );

// Titled with the Schema's name once one is entered.
async function openSchemaEditor( page: Page, schemaName: string ): Promise<Locator> {
	await page.goto( `index.php?title=Schema:${ schemaName }` );
	await page.getByRole( 'button', { name: 'Edit schema' } ).click();
	return page.getByRole( 'dialog', { name: `Editing ${ schemaName } schema` } );
}

function schemaCreator( page: Page ): Locator {
	return page.getByRole( 'dialog', { name: /^Creating (.+ )?schema$/ } );
}

async function scrollTo( scroller: Locator, top: number ): Promise<void> {
	await scroller.evaluate( ( element, scrollTop ) => {
		element.scrollTop = Math.min( scrollTop, element.scrollHeight );
	}, top );
}

async function fadeDepths( scroller: Locator ): Promise<{ start: number; end: number }> {
	return scroller.evaluate( ( element ) => {
		const depth = ( pseudoElement: string ): number => {
			const style = getComputedStyle( element, pseudoElement );
			return Math.round( new DOMMatrixReadOnly( style.transform ).d * parseFloat( style.height ) );
		};
		return { start: depth( '::before' ), end: depth( '::after' ) };
	} );
}
