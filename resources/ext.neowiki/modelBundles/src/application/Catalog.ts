import { sameBundleName } from './bundleName';

export type CatalogEntryKind = 'model' | 'destination';

/**
 * A bundle a catalog lists: the name its file gives it, where that file is, and the entries it requires.
 */
export interface CatalogEntry {
	readonly url: string;
	readonly name: string;
	readonly description: string;
	readonly kind: CatalogEntryKind;
	/** The names of other entries. */
	readonly requires: readonly string[];
}

/**
 * The entries to install for the chosen ones, each after those it requires. A required entry the wiki has
 * installed is left out, a chosen one kept, to be updated.
 */
export function installOrder(
	chosen: readonly CatalogEntry[],
	entries: readonly CatalogEntry[],
	installedNames: readonly string[],
): CatalogEntry[] {
	const order: CatalogEntry[] = [];
	const isInstalled = ( name: string ): boolean => installedNames.some( ( installed ) => sameBundleName( installed, name ) );
	const isListed = ( name: string ): boolean => order.some( ( listed ) => sameBundleName( listed.name, name ) );

	function add( entry: CatalogEntry ): void {
		if ( isListed( entry.name ) ) {
			return;
		}

		for ( const name of entry.requires ) {
			const required = entries.find( ( candidate ) => sameBundleName( candidate.name, name ) );

			if ( required === undefined ) {
				throw new Error( mw.msg( 'neowiki-bundle-catalog-missing-requirement', entry.name, name ) );
			}

			if ( !isInstalled( required.name ) ) {
				add( required );
			}
		}

		order.push( entry );
	}

	chosen.forEach( add );
	return order;
}
