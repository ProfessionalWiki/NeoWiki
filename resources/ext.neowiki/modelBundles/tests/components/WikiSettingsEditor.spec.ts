import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import type { PageValues } from 'ext.neowiki';
import WikiSettingsEditor from '../../src/components/WikiSettingsEditor.vue';
import type { SetupConfig } from '../../src/presentation/setupConfig.ts';
import { createI18nMock, setupMwMock } from '../../../tests/VueTestHelpers.ts';

enableAutoUnmount( afterEach );

const SETUP: SetupConfig = {
	wikiHasSchemas: false,
	wikiHasSubjects: false,
	canEditSettings: true,
	settings: {
		revisionId: 7,
		pageValues: { subjectFirst: null, dataLanguages: null, enforceValidation: true, autoRenderMainSubject: null },
		localSettingsValues: { subjectFirst: false, dataLanguages: [ 'en' ], enforceValidation: false, autoRenderMainSubject: true },
	},
};

function mountEditor( values: PageValues, setup: SetupConfig = SETUP ): VueWrapper {
	setupMwMock( { functions: [ 'msg', 'message', 'config', 'util', 'language' ], config: { wgNeoWikiModelBundlesSetup: setup } } );

	return mount( WikiSettingsEditor, {
		props: { modelValue: values },
		global: { mocks: { $i18n: createI18nMock() }, stubs: { CdxIcon: true } },
	} );
}

function wikiTypeRadio( wrapper: VueWrapper, subjectFirst: boolean ): HTMLInputElement {
	return wrapper.findAll<HTMLInputElement>( 'input[type="radio"]' )[ subjectFirst ? 1 : 0 ].element;
}

describe( 'WikiSettingsEditor', () => {

	it( 'shows the settings in effect where the page leaves them to LocalSettings.php', () => {
		const wrapper = mountEditor( SETUP.settings.pageValues );

		expect( [ wikiTypeRadio( wrapper, false ).checked, wikiTypeRadio( wrapper, true ).checked ] ).toEqual( [ true, false ] );
	} );

	it( 'shows the settings it is given', () => {
		const wrapper = mountEditor( { ...SETUP.settings.pageValues, subjectFirst: true } );

		expect( wikiTypeRadio( wrapper, true ).checked ).toBe( true );
	} );

	it( 'sets the wiki type chosen, leaving the rest as it was', async () => {
		const wrapper = mountEditor( SETUP.settings.pageValues );

		await wrapper.findAll( 'input[type="radio"]' )[ 1 ].setValue( true );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ { ...SETUP.settings.pageValues, subjectFirst: true } ] ] );
	} );

	it( 'offers no change where the settings cannot be changed here', () => {
		const wrapper = mountEditor( SETUP.settings.pageValues, { ...SETUP, canEditSettings: false } );

		expect( wikiTypeRadio( wrapper, true ).disabled ).toBe( true );
	} );

	it( 'goes on when told to', async () => {
		const wrapper = mountEditor( SETUP.settings.pageValues );

		await wrapper.find( '.ext-neowiki-wiki-settings__continue' ).trigger( 'click' );

		expect( wrapper.emitted( 'continue' ) ).toHaveLength( 1 );
	} );

} );
