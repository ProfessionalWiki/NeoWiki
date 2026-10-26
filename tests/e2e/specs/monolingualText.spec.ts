import type { Locator } from '@playwright/test';
import { test, expect } from '../fixtures';
import { createSchema, createSubjectPage, uniqueName } from '../wikiApi';

test( 'the editor shows a text\'s language inside its text field', async ( { page, request } ) => {
	const schemaName = uniqueName( 'Painting' );
	const pageTitle = uniqueName( 'The Starry Night' );
	await createSchema( request, schemaName, { Title: { type: 'monolingualText', multiple: true } } );
	await createSubjectPage( request, {
		pageTitle,
		label: 'The Starry Night',
		schema: schemaName,
		statements: {
			Title: { propertyType: 'monolingualText', value: [ { text: 'The Starry Night', language: 'en' } ] }
		}
	} );

	await page.goto( `index.php?title=${ encodeURIComponent( pageTitle ) }` );
	await page.getByRole( 'button', { name: 'Edit', exact: true } ).click();
	const dialog = page.getByRole( 'dialog', { name: 'Edit subject' } );
	const text = dialog.getByRole( 'textbox', { name: 'Title text 1' } );
	const language = dialog.getByRole( 'combobox', { name: 'Title language 1' } );

	expect( await isInside( language, text ) ).toBe( true );
} );

async function isInside( inner: Locator, outer: Locator ): Promise<boolean> {
	const innerBox = ( await inner.boundingBox() )!;
	const outerBox = ( await outer.boundingBox() )!;

	return innerBox.x >= outerBox.x &&
		innerBox.y >= outerBox.y &&
		innerBox.x + innerBox.width <= outerBox.x + outerBox.width &&
		innerBox.y + innerBox.height <= outerBox.y + outerBox.height;
}
