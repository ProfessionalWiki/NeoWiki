import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { otherVersionAtSource } from '../../src/persistence/sourceVersion';
import { installed } from '../application/builders';
import { setupMwMock } from '../../../tests/VueTestHelpers';

const SOURCE = 'https://example.org/vocabularies.json';

function serve( file: unknown ): void {
	vi.stubGlobal( 'fetch', async () => new Response( JSON.stringify( file ) ) );
}

describe( 'otherVersionAtSource', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
	} );

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it( 'tells of another version at the source, also one this wiki cannot read whole', async () => {
		serve( { bundle: { name: 'Vocabularies', version: '3' }, schemas: { 'Gender Types': 'from a later format' } } );

		expect( await otherVersionAtSource( installed( [], [], { source: SOURCE } ) ) ).toBe( '3' );
	} );

	it( 'tells of none at a source that holds another bundle', async () => {
		serve( { bundle: { name: 'Agents', version: '3' }, schemas: {} } );

		expect( await otherVersionAtSource( installed( [], [], { source: SOURCE } ) ) ).toBeNull();
	} );

} );
