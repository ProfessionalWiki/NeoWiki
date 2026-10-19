import { test, expect } from '../fixtures';
import { createSchema, pageJson, uniqueName } from '../wikiApi';

test( 'a new Schema saves a Relation property with its target Schema', async ( { page, request } ) => {
	const targetName = uniqueName( 'Target' );
	const schemaName = uniqueName( 'Source' );
	await createSchema( request, targetName, {} );

	await page.goto( 'index.php?title=Special:Schemas' );
	await page.getByRole( 'button', { name: 'Create schema' } ).click();
	const dialog = page.getByRole( 'dialog', { name: 'Create schema' } );
	await dialog.getByRole( 'textbox', { name: 'Schema name' } ).fill( schemaName );
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
