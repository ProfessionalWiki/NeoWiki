/* eslint-disable security/detect-non-literal-fs-filename -- the paths come from the repository, not from users */
import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, it } from 'vitest';

// Vitest runs from resources/ext.neowiki, so the extension root is two levels up.
const EXTENSION_ROOT = resolve( process.cwd(), '../..' );

function codexComponentsOfTheModule(): string[] {
	const definition = JSON.parse( readFileSync( resolve( EXTENSION_ROOT, 'extension-model-bundles.json' ), 'utf8' ) );
	return definition.ResourceModules[ 'ext.neowiki.modelBundles' ].codexComponents;
}

function codexComponentsTheSourcesImport(): string[] {
	const sources = readdirSync( resolve( process.cwd(), 'modelBundles/src' ), { recursive: true, encoding: 'utf8' } )
		.filter( ( path ) => path.endsWith( '.vue' ) || path.endsWith( '.ts' ) )
		.map( ( path ) => readFileSync( resolve( process.cwd(), 'modelBundles/src', path ), 'utf8' ) );
	const imports = sources.flatMap( ( source ) => [ ...source.matchAll( /import\s*{([^}]*)}\s*from\s*'@wikimedia\/codex'/g ) ] );

	return [ ...new Set( imports.flatMap( ( match ) => match[ 1 ].match( /\bCdx\w+/g ) ?? [] ) ) ];
}

it( 'ships every Codex component its code uses, as MediaWiki serves no other', () => {
	expect( codexComponentsOfTheModule() ).toEqual( expect.arrayContaining( codexComponentsTheSourcesImport() ) );
} );
