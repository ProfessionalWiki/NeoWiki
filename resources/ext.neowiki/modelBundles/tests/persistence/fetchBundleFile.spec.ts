import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchBundleFile } from '../../src/persistence/fetchBundleFile';
import { setupMwMock } from '../../../tests/VueTestHelpers';

describe( 'fetchBundleFile', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it( 'says how the server refused the file', async () => {
		vi.stubGlobal( 'fetch', async (): Promise<Response> => new Response( 'Not found', { status: 404 } ) );

		await expect( fetchBundleFile( 'https://example.org/model.json' ) ).rejects.toThrow( 'neowiki-bundle-url-failed404' );
	} );

} );
