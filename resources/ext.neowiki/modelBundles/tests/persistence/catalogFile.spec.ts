import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { parseCatalog, readCatalogs, readEntryBundles } from '../../src/persistence/catalogFile';
import { setupMwMock } from '../../../tests/VueTestHelpers';

function catalogOf( ...bundles: unknown[] ): string {
	return JSON.stringify( { bundles } );
}

describe( 'parseCatalog', () => {

	it( 'reads each entry, as a model that requires nothing unless it says otherwise', () => {
		expect( parseCatalog( catalogOf(
			{ url: 'https://example.org/core.json', name: 'Core' },
			{ url: 'https://example.org/edm.json', name: 'EDM', description: 'Exports as EDM', kind: 'destination', requires: [ 'Core' ] },
		) ) ).toEqual( [
			{ url: 'https://example.org/core.json', name: 'Core', description: '', kind: 'model', requires: [] },
			{ url: 'https://example.org/edm.json', name: 'EDM', description: 'Exports as EDM', kind: 'destination', requires: [ 'Core' ] },
		] );
	} );

	it.each( [
		[ 'no URL', { name: 'Core' } ],
		// eslint-disable-next-line no-script-url
		[ 'a URL that is not a web address', { url: 'javascript:alert(1)', name: 'Core' } ],
		[ 'no name', { url: 'https://example.org/core.json' } ],
		[ 'a name no bundle can have', { url: 'https://example.org/core.json', name: 'Core/Extra' } ],
		[ 'a kind of its own', { url: 'https://example.org/core.json', name: 'Core', kind: 'template' } ],
	] )( 'leaves out an entry with %s', ( _case, entry ) => {
		expect( parseCatalog( catalogOf( entry ) ) ).toEqual( [] );
	} );

	it.each( [
		[ 'text that is not JSON', '{ "bundles": [' ],
		[ 'a file without a list of bundles', '{ "bundles": {} }' ],
	] )( 'rejects %s', ( _case, text ) => {
		expect( () => parseCatalog( text ) ).toThrow();
	} );

} );

describe( 'readCatalogs', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	function serve( files: Record<string, string> ): void {
		vi.stubGlobal( 'fetch', async ( url: string ): Promise<Response> => url in files ?
			new Response( files[ url ] ) :
			new Response( 'Not found', { status: 404 } ) );
	}

	it( 'lists the entries of every catalog, the first catalog naming a bundle winning', async () => {
		serve( {
			'https://a.example/catalog.json': catalogOf( { url: 'https://a.example/core.json', name: 'Core' } ),
			'https://b.example/catalog.json': catalogOf(
				{ url: 'https://b.example/core.json', name: 'Core' },
				{ url: 'https://b.example/edm.json', name: 'EDM' },
			),
		} );

		const catalogs = await readCatalogs( [ 'https://a.example/catalog.json', 'https://b.example/catalog.json' ] );

		expect( catalogs.entries.map( ( entry ) => entry.url ) ).toEqual( [ 'https://a.example/core.json', 'https://b.example/edm.json' ] );
	} );

	it( 'tells of a catalog it could not read, with the status its server answered, and lists the others', async () => {
		serve( { 'https://b.example/catalog.json': catalogOf( { url: 'https://b.example/edm.json', name: 'EDM' } ) } );

		const catalogs = await readCatalogs( [ 'https://a.example/catalog.json', 'https://b.example/catalog.json' ] );

		expect( catalogs ).toMatchObject( {
			entries: [ { name: 'EDM' } ],
			unreadable: [ { url: 'https://a.example/catalog.json', reason: 'neowiki-bundle-catalog-httphttps://a.example/catalog.json404' } ],
		} );
	} );

	it( 'tells, of a catalog it could not reach, that its server may not let other websites read it', async () => {
		vi.stubGlobal( 'fetch', async (): Promise<Response> => {
			throw new TypeError( 'Failed to fetch' );
		} );

		const catalogs = await readCatalogs( [ 'https://a.example/catalog.json' ] );

		expect( catalogs.unreadable ).toEqual( [
			{ url: 'https://a.example/catalog.json', reason: 'neowiki-bundle-catalog-unreachablehttps://a.example/catalog.json' },
		] );
	} );

	it( 'tells of a file that is not a catalog', async () => {
		serve( { 'https://a.example/catalog.json': '{ "bundles": {} }' } );

		const catalogs = await readCatalogs( [ 'https://a.example/catalog.json' ] );

		expect( catalogs.unreadable ).toEqual( [
			{ url: 'https://a.example/catalog.json', reason: 'neowiki-bundle-catalog-invalidhttps://a.example/catalog.json' },
		] );
	} );

} );

describe( 'readEntryBundles', () => {

	const CORE = { url: 'https://example.org/core.json', name: 'Core', description: '', kind: 'model' as const, requires: [] };

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	function serveFile( bundleName: string ): void {
		vi.stubGlobal( 'fetch', async (): Promise<Response> => new Response( JSON.stringify( {
			bundle: { name: bundleName, version: '1' },
			schemas: { Agent: { propertyDefinitions: {} } },
		} ) ) );
	}

	it( 'reads the bundle of each entry, to install from the entry\'s URL', async () => {
		serveFile( 'Core' );

		expect( await readEntryBundles( [ CORE ] ) ).toMatchObject( [ { name: 'Core', source: 'https://example.org/core.json' } ] );
	} );

	it( 'refuses a file that holds another bundle than the entry names', async () => {
		serveFile( 'Agents' );

		await expect( readEntryBundles( [ CORE ] ) ).rejects.toThrow( 'neowiki-bundle-catalog-wrong-fileCorehttps://example.org/core.json' );
	} );

} );
