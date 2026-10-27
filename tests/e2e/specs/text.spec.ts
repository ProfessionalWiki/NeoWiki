import { test, expect } from '../fixtures';
import { createSchema, createSubjectPage, uniqueName } from '../wikiApi';

test( 'the editor flags a value repeated in a multi-valued text property', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Painting' );
	const pageTitle = uniqueName( 'The Starry Night' );
	await createSchema( request, schemaName, { Tags: { type: 'text', multiple: true, uniqueItems: true } } );
	await createSubjectPage( request, {
		pageTitle,
		label: 'The Starry Night',
		schema: schemaName,
		statements: {
			Tags: { propertyType: 'text', value: [ 'Night' ] }
		}
	} );

	await page.goto( `index.php?title=${ encodeURIComponent( pageTitle ) }` );
	await page.getByRole( 'button', { name: 'Edit', exact: true } ).click();
	const dialog = page.getByRole( 'dialog', { name: 'Edit subject' } );
	await dialog.getByRole( 'textbox', { name: 'Tags item 2' } ).fill( 'Night' );

	await expect( dialog.getByRole( 'group', { name: 'Tags (optional)' } ) )
		.toContainText( 'All values must be unique.' );
} );
