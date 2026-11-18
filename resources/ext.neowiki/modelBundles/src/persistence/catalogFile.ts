import { isHttpUrl } from 'ext.neowiki';
import { isJsonObject } from '../application/canonical';
import { isUsableBundleName, sameBundleName } from '../application/bundleName';
import type { CatalogEntry } from '../application/Catalog';
import type { BundleToInstall } from '../application/ModelBundle';
import { parseBundleFile } from './bundleFile';
import { fetchBundleFile } from './fetchBundleFile';

const KINDS = [ 'model', 'destination' ];

/**
 * Throws an Error for text that is not a catalog. An entry it cannot use is left out, so that one broken entry
 * does not hide the others.
 */
export function parseCatalog( text: string ): CatalogEntry[] {
	const file: unknown = JSON.parse( text );

	if ( !isJsonObject( file ) || !Array.isArray( file.bundles ) ) {
		throw new Error( 'A catalog has a list of bundles.' );
	}

	return file.bundles.flatMap( entryOf );
}

function entryOf( json: unknown ): CatalogEntry[] {
	if ( !isJsonObject( json ) || typeof json.url !== 'string' || !isHttpUrl( json.url ) ||
		typeof json.name !== 'string' || !isUsableBundleName( json.name ) ) {
		return [];
	}

	const kind = json.kind ?? 'model';

	if ( typeof kind !== 'string' || !KINDS.includes( kind ) ) {
		return [];
	}

	return [ {
		url: json.url,
		name: json.name,
		description: typeof json.description === 'string' ? json.description : '',
		kind: kind as CatalogEntry[ 'kind' ],
		requires: Array.isArray( json.requires ) ? json.requires.filter( ( name ) => typeof name === 'string' ) : [],
	} ];
}

export interface Catalogs {
	readonly entries: CatalogEntry[];
	/** The URLs of the catalogs that could not be read. */
	readonly unreadable: string[];
}

/**
 * Reads the catalogs in the browser, which takes their servers to let other websites read them. A bundle two
 * catalogs list is taken from the first.
 */
export async function readCatalogs( urls: readonly string[] ): Promise<Catalogs> {
	const read = await Promise.all( urls.map( readCatalog ) );
	const entries: CatalogEntry[] = [];

	for ( const entry of read.flatMap( ( catalog ) => catalog ?? [] ) ) {
		if ( !entries.some( ( listed ) => sameBundleName( listed.name, entry.name ) ) ) {
			entries.push( entry );
		}
	}

	return { entries, unreadable: urls.filter( ( _url, index ) => read[ index ] === null ) };
}

/**
 * Reads the file of each entry, refusing one that holds another bundle than the entry names.
 */
export function readEntryBundles( entries: readonly CatalogEntry[] ): Promise<BundleToInstall[]> {
	return Promise.all( entries.map( async ( entry ): Promise<BundleToInstall> => {
		const bundle = parseBundleFile( await fetchBundleFile( entry.url ) );

		if ( bundle.metadata === null || !sameBundleName( bundle.metadata.name, entry.name ) ) {
			throw new Error( mw.msg( 'neowiki-bundle-catalog-wrong-file', entry.name, entry.url ) );
		}

		return { bundle, name: entry.name, source: entry.url };
	} ) );
}

async function readCatalog( url: string ): Promise<CatalogEntry[] | null> {
	try {
		const response = await fetch( url, { cache: 'no-cache' } );
		return response.ok ? parseCatalog( await response.text() ) : null;
	} catch ( _error ) {
		return null;
	}
}
