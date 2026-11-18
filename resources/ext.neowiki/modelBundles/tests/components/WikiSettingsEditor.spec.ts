import { enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import WikiSettingsEditor from '../../src/components/WikiSettingsEditor.vue';
import { ModelBundleService } from '../../src/ModelBundleServices.ts';
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

let saves: { page: string; source: string; baseRevisionId: number | null }[];
let saveFailure: string | null;

function mountEditor( setup: SetupConfig = SETUP ): VueWrapper {
	setupMwMock( { functions: [ 'msg', 'message', 'config', 'util', 'language' ], config: { wgNeoWikiModelBundlesSetup: setup } } );

	return mountEditorAgain();
}

/**
 * As the setup shows the step once more, before the page reloads.
 */
function mountEditorAgain(): VueWrapper {
	return mount( WikiSettingsEditor, {
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ ModelBundleService.PageSaver ]: {
					saveRevision: async ( page: string, source: string, _summary: string, _model: string, baseRevisionId: number | null ) => {
						saves.push( { page, source, baseRevisionId } );
						return saveFailure === null ?
							{ success: true, revisionId: 8, source } :
							{ success: false, message: saveFailure };
					},
				},
			},
			stubs: { CdxIcon: true },
		},
	} );
}

function wikiTypeRadio( wrapper: VueWrapper, subjectFirst: boolean ): HTMLInputElement {
	return wrapper.findAll<HTMLInputElement>( 'input[type="radio"]' )[ subjectFirst ? 1 : 0 ].element;
}

async function chooseSubjectFirst( wrapper: VueWrapper ): Promise<void> {
	await wrapper.findAll( 'input[type="radio"]' )[ 1 ].setValue( true );
}

async function next( wrapper: VueWrapper ): Promise<void> {
	await wrapper.find( '.ext-neowiki-wiki-settings__continue' ).trigger( 'click' );
	await flushPromises();
}

describe( 'WikiSettingsEditor', () => {

	beforeEach( () => {
		saves = [];
		saveFailure = null;
	} );

	it( 'starts from the settings in effect', () => {
		const wrapper = mountEditor();

		expect( [ wikiTypeRadio( wrapper, false ).checked, wikiTypeRadio( wrapper, true ).checked ] ).toEqual( [ true, false ] );
	} );

	it( 'saves only what was changed, leaving the rest as it was', async () => {
		const wrapper = mountEditor();

		await chooseSubjectFirst( wrapper );
		await next( wrapper );

		expect( saves.map( ( save ) => ( { ...save, source: JSON.parse( save.source ) } ) ) ).toEqual( [ {
			page: 'MediaWiki:NeoWiki',
			source: { subjectFirst: true, enforceValidation: true },
			baseRevisionId: 7,
		} ] );
		expect( wrapper.emitted( 'done' ) ).toHaveLength( 1 );
	} );

	it( 'saves nothing when nothing was changed', async () => {
		const wrapper = mountEditor();

		await next( wrapper );

		expect( saves ).toEqual( [] );
		expect( wrapper.emitted( 'done' ) ).toHaveLength( 1 );
	} );

	it( 'tells the later steps the wiki type chosen', async () => {
		const wrapper = mountEditor();

		await chooseSubjectFirst( wrapper );
		await next( wrapper );

		expect( mw.config.get( 'wgNeoWikiSubjectFirst' ) ).toBe( true );
	} );

	it( 'shows the settings without offering to change them where they cannot be changed here', async () => {
		const wrapper = mountEditor( { ...SETUP, canEditSettings: false } );

		await next( wrapper );

		expect( wikiTypeRadio( wrapper, true ).disabled ).toBe( true );
		expect( saves ).toEqual( [] );
		expect( wrapper.emitted( 'done' ) ).toHaveLength( 1 );
	} );

	it( 'starts again from what it saved when it is shown once more', async () => {
		const first = mountEditor();
		await chooseSubjectFirst( first );
		await next( first );
		first.unmount();

		const again = mountEditorAgain();

		expect( wikiTypeRadio( again, true ).checked ).toBe( true );
	} );

	it( 'saves a later change over the revision it saved', async () => {
		const first = mountEditor();
		await chooseSubjectFirst( first );
		await next( first );
		first.unmount();
		const again = mountEditorAgain();

		await again.findAll( 'input[type="radio"]' )[ 0 ].setValue( true );
		await next( again );

		expect( saves.map( ( save ) => save.baseRevisionId ) ).toEqual( [ 7, 8 ] );
	} );

	it( 'stays, saying why, when the settings could not be saved', async () => {
		saveFailure = 'Edit conflict';
		const wrapper = mountEditor();

		await chooseSubjectFirst( wrapper );
		await next( wrapper );

		expect( wrapper.find( '.cdx-message--error' ).text() ).toBe( 'Edit conflict' );
		expect( wrapper.emitted( 'done' ) ).toBeUndefined();
	} );

} );
