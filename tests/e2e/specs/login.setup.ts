import { test as setup } from '@playwright/test';
import { adminPassword, adminStorageState, adminUser } from '../playwright.config';

setup( 'log in as admin', async ( { page } ) => {
	await page.goto( 'index.php?title=Special:UserLogin' );
	await page.getByLabel( 'Username' ).fill( adminUser );
	await page.getByLabel( 'Password' ).fill( adminPassword );
	await page.getByRole( 'button', { name: 'Log in' } ).click();
	await page.waitForURL( ( url ) => !url.searchParams.has( 'title', 'Special:UserLogin' ) );

	await page.context().storageState( { path: adminStorageState } );
} );
