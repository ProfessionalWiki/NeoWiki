/* eslint-disable security/detect-non-literal-fs-filename -- the paths come from the tests, not from users */
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Content as releases stored it, in tests/release-content/<release>/<content model>/<page>.json, which the
 * PHP suite reads too. A test failing on it means master can no longer read what wikis of that release hold; never
 * edit the files to make it pass. Vitest runs from resources/ext.neowiki, so the extension root is two levels up.
 */
const ROOT = resolve( process.cwd(), '../../tests/release-content' );

export function readReleaseContent( release: string, contentModel: string, page: string ): Record<string, any> {
	return JSON.parse( readFileSync( resolve( ROOT, release, contentModel, `${ page.replace( / /g, '_' ) }.json` ), 'utf8' ) );
}

/**
 * The release and page of each file of the content model.
 */
export function releaseContentPages( contentModel: string ): { release: string; page: string }[] {
	return readdirSync( ROOT )
		.filter( ( release ) => existsSync( resolve( ROOT, release, contentModel ) ) )
		.flatMap( ( release ) => readdirSync( resolve( ROOT, release, contentModel ) ).map(
			( file ) => ( { release, page: file.replace( /\.json$/, '' ).replace( /_/g, ' ' ) } ),
		) );
}
