import type { APIRequestContext, Locator, Page } from '@playwright/test';
import { test, expect } from '../fixtures';
import { createSchema, createSubjectPage, setStatement, uniqueName } from '../wikiApi';

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

test.describe( 'a Subject saved elsewhere while it is being edited', () => {
	async function editFoundedWhileChangedElsewhere(
		page: Page,
		request: APIRequestContext,
		propertyName: string,
		statement: object
	): Promise<Locator> {
		const schemaName = uniqueName( 'Company' );
		const pageTitle = uniqueName( 'Acme' );
		await createSchema( request, schemaName, companyProperties );
		const subjectId = await createSubjectPage( request, {
			pageTitle,
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

		await setStatement( request, subjectId, propertyName, statement );

		await dialog.getByRole( 'button', { name: 'Save changes' } ).click();

		return dialog;
	}

	test( 'keeps a change made elsewhere to another field beside the save', async ( { page, request } ) => {
		const dialog = await editFoundedWhileChangedElsewhere( page, request, 'Motto', { propertyType: 'text', value: [ 'Changed elsewhere' ] } );

		await expect( dialog ).toBeHidden();
		await page.reload();

		await expect( infoboxValue( page, 'Founded' ) ).toHaveText( '2001' );
		await expect( infoboxValue( page, 'Motto' ) ).toHaveText( 'Changed elsewhere' );
	} );

	test.describe( 'when both changed the same field', () => {
		// The first save is refused with this status.
		test.use( { expectedErrorStatus: 409 } );

		test( 'saves the value chosen', async ( { page, request } ) => {
			const dialog = await editFoundedWhileChangedElsewhere( page, request, 'Founded', { propertyType: 'number', value: 2002 } );
			await expect( dialog.getByText( 'Acme Corporation changed while you were editing' ) ).toBeVisible();

			await dialog.locator( 'label', { hasText: 'Yours' } ).click();
			await dialog.getByRole( 'button', { name: 'Save changes' } ).click();
			await expect( dialog ).toBeHidden();
			await page.reload();

			await expect( infoboxValue( page, 'Founded' ) ).toHaveText( '2001' );
		} );
	} );
} );

// The infobox lists its values in plain elements, with no table or list roles to find them by.
function infoboxValue( page: Page, propertyName: string ): Locator {
	return page.locator( '.ext-neowiki-infobox__item' )
		.filter( { has: page.locator( '.ext-neowiki-infobox__property', { hasText: propertyName } ) } )
		.locator( '.ext-neowiki-infobox__value' );
}
