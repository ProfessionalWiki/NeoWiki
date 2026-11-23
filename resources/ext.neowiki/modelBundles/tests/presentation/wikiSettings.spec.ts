import { beforeEach, describe, expect, it } from 'vitest';
import type { PageValues } from 'ext.neowiki';
import { saveSettings, settingsLine } from '../../src/presentation/wikiSettings';
import type { SetupConfig } from '../../src/presentation/setupConfig';
import { setupMwMock } from '../../../tests/VueTestHelpers';

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

const SUBJECT_FIRST: PageValues = { ...SETUP.settings.pageValues, subjectFirst: true };

let saves: { page: string; source: string; baseRevisionId: number | null }[];
let saveFailure: string | null;

const saver = {
	saveRevision: async ( page: string, source: string, _summary: string, _model: string, baseRevisionId: number | null ) => {
		saves.push( { page, source, baseRevisionId } );
		return saveFailure === null ? { success: true as const, revisionId: 8, source } : { success: false as const, message: saveFailure };
	},
};

function mockSetup( setup: SetupConfig ): void {
	setupMwMock( { functions: [ 'msg', 'config', 'language' ], config: { wgNeoWikiModelBundlesSetup: setup, wgContentLanguage: 'en' } } );
}

describe( 'saveSettings', () => {

	beforeEach( () => {
		saves = [];
		saveFailure = null;
		mockSetup( SETUP );
	} );

	it( 'saves only what was changed, leaving the rest as it was', async () => {
		await saveSettings( SUBJECT_FIRST, saver );

		expect( saves.map( ( save ) => ( { ...save, source: JSON.parse( save.source ) } ) ) ).toEqual( [ {
			page: 'MediaWiki:NeoWiki',
			source: { subjectFirst: true, enforceValidation: true },
			baseRevisionId: 7,
		} ] );
	} );

	it( 'saves nothing when nothing was changed', async () => {
		await saveSettings( { ...SETUP.settings.pageValues }, saver );

		expect( saves ).toEqual( [] );
	} );

	it( 'saves nothing where the settings cannot be changed here', async () => {
		mockSetup( { ...SETUP, canEditSettings: false } );

		await saveSettings( SUBJECT_FIRST, saver );

		expect( saves ).toEqual( [] );
	} );

	it( 'tells the frontend the wiki type it saved', async () => {
		await saveSettings( SUBJECT_FIRST, saver );

		expect( mw.config.get( 'wgNeoWikiSubjectFirst' ) ).toBe( true );
	} );

	it( 'says why the settings could not be saved', async () => {
		saveFailure = 'Edit conflict';

		expect( await saveSettings( SUBJECT_FIRST, saver ) ).toBe( 'Edit conflict' );
	} );

	it( 'saves a later change over the revision it saved', async () => {
		await saveSettings( SUBJECT_FIRST, saver );

		await saveSettings( { ...SUBJECT_FIRST, subjectFirst: false }, saver );

		expect( saves.map( ( save ) => save.baseRevisionId ) ).toEqual( [ 7, 8 ] );
	} );

	it( 'saves nothing again for what it saved', async () => {
		await saveSettings( SUBJECT_FIRST, saver );

		await saveSettings( SUBJECT_FIRST, saver );

		expect( saves ).toHaveLength( 1 );
	} );

} );

describe( 'settingsLine', () => {

	beforeEach( () => {
		mockSetup( SETUP );
	} );

	it( 'names the wiki type and the data languages in effect', () => {
		expect( settingsLine( { ...SUBJECT_FIRST, dataLanguages: [ 'de', 'en' ] } ) ).toBe(
			'neowiki-bundle-setup-review-settingsneowiki-config-setting-subject-first-subject-firstGerman and English',
		);
	} );

	it( 'names the languages LocalSettings.php gives where the page leaves them to it', () => {
		expect( settingsLine( SETUP.settings.pageValues ) )
			.toBe( 'neowiki-bundle-setup-review-settingsneowiki-config-setting-subject-first-page-firstEnglish' );
	} );

} );
