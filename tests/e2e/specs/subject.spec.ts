import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../fixtures';
import { createSchema, createSubjectPage, uniqueName } from '../wikiApi';

const companyProperties = {
	Founded: { type: 'number' },
	Motto: { type: 'text' }
};

test( 'a Subject created on a new page shows its values there', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Company' );
	await createSchema( request, schemaName, companyProperties );

	await page.goto( 'index.php?title=Special:CreateSubject' );
	const dialog = page.getByRole( 'dialog', { name: 'Creating subject' } );
	await dialog.getByRole( 'combobox', { name: 'Schema' } ).pressSequentially( schemaName );
	await page.getByRole( 'option', { name: schemaName } ).click();
	await dialog.getByRole( 'textbox', { name: 'Label (optional)' } ).fill( uniqueName( 'Acme' ) );
	await dialog.getByRole( 'spinbutton', { name: 'Founded (optional)' } ).fill( '1999' );
	await dialog.getByRole( 'group', { name: 'Motto (optional)' } ).getByRole( 'textbox' ).fill( 'Make things' );
	await dialog.getByRole( 'button', { name: 'Create subject' } ).click();

	await expect( infoboxValue( page, 'Founded' ) ).toHaveText( '1999' );
	await expect( infoboxValue( page, 'Motto' ) ).toHaveText( 'Make things' );
} );

test( 'an edited Subject shows and keeps its new value without losing the rest', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Company' );
	const pageTitle = uniqueName( 'Acme' );
	await createSchema( request, schemaName, companyProperties );
	await createSubjectPage( request, {
		pageTitle,
		// Differs from the page title, which a Subject without a label shows instead.
		label: 'Acme Corporation',
		schema: schemaName,
		statements: {
			Founded: { propertyType: 'number', value: 1999 },
			Motto: { propertyType: 'text', value: [ 'Make things' ] }
		}
	} );

	await page.goto( `index.php?title=${ encodeURIComponent( pageTitle ) }` );
	await page.getByRole( 'button', { name: 'Edit', exact: true } ).click();
	const dialog = page.getByRole( 'dialog', { name: 'Edit subject' } );
	await dialog.getByRole( 'spinbutton', { name: 'Founded (optional)' } ).fill( '2001' );
	await dialog.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect( dialog ).toBeHidden();

	await expect( infoboxValue( page, 'Founded' ) ).toHaveText( '2001' );

	await page.reload();

	await expect( infoboxValue( page, 'Founded' ) ).toHaveText( '2001' );
	await expect( infoboxValue( page, 'Motto' ) ).toHaveText( 'Make things' );
	await expect( page.getByRole( 'heading', { name: 'Acme Corporation' } ) ).toBeVisible();
} );

// The infobox lists its values in plain elements, with no table or list roles to find them by.
function infoboxValue( page: Page, propertyName: string ): Locator {
	return page.locator( '.ext-neowiki-infobox__item' )
		.filter( { has: page.locator( '.ext-neowiki-infobox__property', { hasText: propertyName } ) } )
		.locator( '.ext-neowiki-infobox__value' );
}
