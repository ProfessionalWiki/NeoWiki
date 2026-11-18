import { enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ModelBundlesNotice from '../../src/components/ModelBundlesNotice.vue';
import { ModelBundleService } from '../../src/ModelBundleServices.ts';
import type { InstallRecord } from '../../src/application/InstallRecord.ts';
import { createI18nMock, setupMwMock } from '../../../tests/VueTestHelpers.ts';
import { installed } from '../application/builders.ts';

enableAutoUnmount( afterEach );

async function mountNotice( setupOffered: boolean, records: InstallRecord[] = [] ): Promise<VueWrapper> {
	setupMwMock( { functions: [ 'msg', 'message', 'config', 'util' ], config: { wgNeoWikiModelBundlesSetupOffered: setupOffered } } );

	const wrapper = mount( ModelBundlesNotice, {
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: { [ ModelBundleService.InstallRecordRepository ]: { getInstallRecords: async () => records } },
			stubs: { CdxIcon: true },
		},
	} );
	await flushPromises();

	return wrapper;
}

function serveVersions( versions: Record<string, string> ): void {
	vi.stubGlobal( 'fetch', async ( url: string ) => new Response( JSON.stringify( {
		bundle: { name: url.replace( /^.*\//, '' ).replace( '.json', '' ), version: versions[ url ] },
		schemas: { Agent: { propertyDefinitions: {} } },
	} ) ) );
}

describe( 'ModelBundlesNotice', () => {

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it( 'offers the setup of the data model in place of the first steps', async () => {
		const wrapper = await mountNotice( true );

		expect( wrapper.find( 'a.cdx-card' ).attributes( 'href' ) ).toBe( '/wiki/Special:DataModel/setup' );
	} );

	it( 'offers no setup when the page does not offer it', async () => {
		serveVersions( { 'https://example.org/Agents.json': '2' } );

		const wrapper = await mountNotice( false, [ installed( [], [], { name: 'Agents', version: '1', source: 'https://example.org/Agents.json' } ) ] );

		expect( wrapper.find( '.cdx-card' ).exists() ).toBe( false );
	} );

	it( 'counts the installed bundles with another version at their source, leading to the data model', async () => {
		serveVersions( { 'https://example.org/Agents.json': '2', 'https://example.org/Places.json': '1' } );

		const wrapper = await mountNotice( false, [
			installed( [], [], { name: 'Agents', version: '1', source: 'https://example.org/Agents.json' } ),
			installed( [], [], { name: 'Places', version: '1', source: 'https://example.org/Places.json' } ),
			installed( [], [], { name: 'Vocabularies', version: '1', source: 'file' } ),
		] );

		const link = wrapper.find( '.cdx-message a' );
		expect( [ link.text(), link.attributes( 'href' ) ] ).toEqual( [ 'neowiki-bundle-updates-available1', '/wiki/Special:DataModel' ] );
	} );

	it( 'says nothing of updates while no bundle has another version', async () => {
		serveVersions( { 'https://example.org/Agents.json': '1' } );

		const wrapper = await mountNotice( false, [ installed( [], [], { name: 'Agents', version: '1', source: 'https://example.org/Agents.json' } ) ] );

		expect( wrapper.find( '.cdx-message' ).exists() ).toBe( false );
	} );

} );
