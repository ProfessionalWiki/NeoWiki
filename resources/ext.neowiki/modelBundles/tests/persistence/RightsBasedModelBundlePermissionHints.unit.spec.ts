import { describe, expect, it } from 'vitest';
import { RightsBasedModelBundlePermissionHints } from '../../src/persistence/RightsBasedModelBundlePermissionHints';

function hintsFor( ...rights: string[] ): RightsBasedModelBundlePermissionHints {
	return new RightsBasedModelBundlePermissionHints( { getRights: async () => rights, refreshRights: () => Promise.resolve() } );
}

describe( 'RightsBasedModelBundlePermissionHints', () => {

	it( 'lets a user install model bundles who may write sitewide JSON in the MediaWiki namespace', async () => {
		expect( await hintsFor( 'edit', 'editinterface', 'editsitejson' ).canInstallModelBundles() ).toBe( true );
	} );

	it.each( [
		[ 'editsitejson', [ 'editinterface' ] ],
		[ 'editinterface', [ 'editsitejson' ] ],
	] )( 'does not without %s', async ( _missing, rights ) => {
		expect( await hintsFor( ...rights ).canInstallModelBundles() ).toBe( false );
	} );

} );
