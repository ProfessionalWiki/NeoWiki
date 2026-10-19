import { defineConfig } from '@playwright/test';

export const adminStorageState = '.auth/admin.json';
export const adminUser = requiredEnv( 'MEDIAWIKI_USER' );
export const adminPassword = requiredEnv( 'MEDIAWIKI_PASSWORD' );

export default defineConfig( {
	testDir: './specs',
	forbidOnly: !!process.env.CI,
	reporter: process.env.CI ? 'github' : 'list',
	use: {
		baseURL: `${ requiredEnv( 'MW_SERVER' ) }${ requiredEnv( 'MW_SCRIPT_PATH' ) }/`,
		trace: 'retain-on-failure'
	},
	projects: [
		{
			name: 'setup',
			testMatch: /.*\.setup\.ts/
		},
		{
			name: 'chromium',
			use: { storageState: adminStorageState },
			dependencies: [ 'setup' ]
		}
	]
} );

function requiredEnv( name: string ): string {
	const value = process.env[ name ];

	// An empty script path is valid, for a wiki served from the web root.
	if ( value === undefined ) {
		throw new Error( `Set ${ name }; make browser-test sets it for the dev wiki.` );
	}

	return value;
}
