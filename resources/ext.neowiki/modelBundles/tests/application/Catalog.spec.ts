import { beforeEach, describe, expect, it } from 'vitest';
import { type CatalogEntry, installOrder, requirersIn } from '../../src/application/Catalog';
import { setupMwMock } from '../../../tests/VueTestHelpers';

function entry( name: string, requires: string[] = [], kind: CatalogEntry[ 'kind' ] = 'model' ): CatalogEntry {
	return { url: `https://example.org/${ name }.json`, name, description: '', kind, requires };
}

const CORE = entry( 'Core' );
const MUSEUM = entry( 'Museum', [ 'Core' ] );
const EDM = entry( 'EDM', [ 'Core' ], 'destination' );
const CATALOG = [ EDM, MUSEUM, CORE ];

function names( entries: CatalogEntry[] ): string[] {
	return entries.map( ( item ) => item.name );
}

describe( 'installOrder', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	it( 'puts each entry after those it requires, once', () => {
		expect( names( installOrder( [ MUSEUM, EDM ], CATALOG, [] ) ) ).toEqual( [ 'Core', 'Museum', 'EDM' ] );
	} );

	it( 'leaves out a required entry the wiki has installed', () => {
		expect( names( installOrder( [ MUSEUM ], CATALOG, [ 'Core' ] ) ) ).toEqual( [ 'Museum' ] );
	} );

	it( 'keeps a chosen entry the wiki has installed, to update it', () => {
		expect( names( installOrder( [ CORE ], CATALOG, [ 'Core' ] ) ) ).toEqual( [ 'Core' ] );
	} );

	it( 'refuses an entry that requires one no catalog lists', () => {
		expect( () => installOrder( [ entry( 'Library', [ 'Books' ] ) ], CATALOG, [] ) )
			.toThrow( 'neowiki-bundle-catalog-missing-requirementLibraryBooks' );
	} );

} );

describe( 'requirersIn', () => {

	it( 'names, for an entry pulled into the order, the entries in it that require it', () => {
		expect( requirersIn( [ CORE, MUSEUM, EDM ], [ MUSEUM, EDM ] ) ).toEqual( new Map( [ [ 'Core', [ 'Museum', 'EDM' ] ] ] ) );
	} );

	it( 'names none for an entry chosen, though another requires it', () => {
		expect( requirersIn( [ CORE, MUSEUM ], [ CORE, MUSEUM ] ) ).toEqual( new Map() );
	} );

} );
