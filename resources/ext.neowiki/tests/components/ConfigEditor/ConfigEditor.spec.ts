import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ConfigEditor from '@/components/ConfigEditor/ConfigEditor.vue';
import type { ConfigSetting } from '@/components/ConfigEditor/configEditing.ts';
import type { PageRevisionSaver, RevisionSaveStatus } from '@/persistence/PageSaver.ts';
import { listedLanguages, setupMwMock } from '../../VueTestHelpers.ts';

interface Save {
	pageName: string;
	source: string;
	comment: string;
	contentModel: string;
	baseRevisionId: number | null;
}

// A successful save stores the source it is handed unless the outcome names what the page holds after it.
type SaveOutcome =
	| { success: true; revisionId: number; source?: string }
	| { success: false; message: string };

class SpyPageSaver implements PageRevisionSaver {

	public readonly saves: Save[] = [];

	public constructor( private readonly outcomes: SaveOutcome[] = [] ) {
	}

	public async saveRevision(
		pageName: string,
		source: string,
		comment: string,
		contentModel: string,
		baseRevisionId: number | null,
	): Promise<RevisionSaveStatus> {
		this.saves.push( { pageName, source, comment, contentModel, baseRevisionId } );

		const outcome = this.outcomes.shift() ?? { success: true, revisionId: 100 + this.saves.length };

		return outcome.success ? { source, ...outcome } : outcome;
	}

}

class StalledPageSaver implements PageRevisionSaver {

	public saveRevision(): Promise<RevisionSaveStatus> {
		return new Promise( () => {
			// Still saving when the test looks.
		} );
	}

}

const SUBJECT_FIRST: ConfigSetting = {
	key: 'subjectFirst',
	type: 'boolean',
	label: 'Wiki type',
	help: null,
	choices: [
		{ value: false, label: 'Page-first', help: 'A new Subject goes on the page it is created from.' },
		{ value: true, label: 'Subject-first', help: 'Each new Subject gets its own page.' },
	],
	localSettingsValue: false,
	pageValue: null,
};

const AUTO_RENDER: ConfigSetting = {
	key: 'autoRenderMainSubject',
	type: 'boolean',
	label: 'Show Main Subjects as infoboxes',
	help: null,
	choices: [],
	localSettingsValue: true,
	pageValue: false,
};

const DATA_LANGUAGES: ConfigSetting = {
	key: 'dataLanguages',
	type: 'languageList',
	label: 'Data languages',
	help: 'Languages of the wiki\'s data.',
	choices: [],
	localSettingsValue: [ 'de' ],
	pageValue: null,
};

// Mounted with the real controls and Codex components, and attached, because where the focus goes
// is part of what is asserted.
describe( 'ConfigEditor', () => {

	let wrapper: VueWrapper | undefined;

	beforeEach( () => {
		setupMwMock( {
			config: { wgUserLanguage: 'en', wgContentLanguage: 'en' },
			messages: {
				'neowiki-config-editor-reset': 'Reset to default',
				'neowiki-config-editor-reset-label': ( label: string ) => `Reset ${ label } to default`,
				'neowiki-config-editor-saved': 'Settings saved.',
				'neowiki-config-editor-save': 'Save settings',
				'neowiki-config-editor-read-only': 'You do not have permission to change these settings.',
				'neowiki-config-editor-save-failed': ( reason: string ) => `The settings were not saved: ${ reason }`,
				'neowiki-config-editor-changed-meanwhile': 'This page was also changed after the form was loaded.',
			},
		} );
	} );

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
	} );

	function newEditor(
		settings: ConfigSetting[],
		{ canEdit = true, revisionId = 42, pageSaver = new SpyPageSaver() }: {
			canEdit?: boolean;
			revisionId?: number | null;
			pageSaver?: PageRevisionSaver;
		} = {},
	): VueWrapper {
		wrapper = mount( ConfigEditor, {
			props: {
				settings: settings,
				canEdit: canEdit,
				revisionId: revisionId,
				pageName: 'MediaWiki:NeoWiki',
				pageSaver: pageSaver,
			},
			global: {
				mocks: {
					$i18n: ( key: string, ...params: unknown[] ) => mw.message( key, ...params ),
				},
			},
			attachTo: document.body,
		} );
		return wrapper;
	}

	function settingLabelled( editor: VueWrapper, label: string ): DOMWrapper<Element> {
		const setting = editor.findAll( '.ext-neowiki-config-editor__setting' )
			.find( ( candidate ) => candidate.text().includes( label ) );

		expect( setting ).toBeDefined();
		return setting!;
	}

	function checkbox( editor: VueWrapper, label: string ): DOMWrapper<HTMLInputElement> {
		return settingLabelled( editor, label ).find( 'input[type="checkbox"]' );
	}

	function radio( editor: VueWrapper, label: string ): DOMWrapper<HTMLInputElement> {
		const labelElement = editor.findAll( 'label' ).find( ( candidate ) => candidate.text() === label );

		expect( labelElement ).toBeDefined();
		return editor.find( `#${ labelElement!.attributes( 'for' ) }` );
	}

	function resetLink( editor: VueWrapper, label: string ): DOMWrapper<HTMLButtonElement> {
		return settingLabelled( editor, label ).find( '.ext-neowiki-config-editor__reset' );
	}

	function saveButton( editor: VueWrapper ): DOMWrapper<HTMLButtonElement> {
		const button = editor.findAll<HTMLButtonElement>( 'button' ).find( ( candidate ) => candidate.text() === 'Save settings' );

		expect( button ).toBeDefined();
		return button!;
	}

	async function save( editor: VueWrapper ): Promise<void> {
		await saveButton( editor ).trigger( 'click' );
		await flushPromises();
	}

	function summaryInput( editor: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return editor.find<HTMLInputElement>( '.ext-neowiki-config-editor__save input' );
	}

	function savedJson( pageSaver: SpyPageSaver ): unknown {
		return JSON.parse( pageSaver.saves[ 0 ].source );
	}

	it( 'shows a setting with its label and help text', () => {
		const editor = newEditor( [ DATA_LANGUAGES ] );

		expect( settingLabelled( editor, 'Data languages' ).text() ).toContain( 'Languages of the wiki\'s data.' );
	} );

	it( 'offers a setting that names its values as a choice between them, each with its help text', () => {
		const editor = newEditor( [ SUBJECT_FIRST ] );

		expect( radio( editor, 'Page-first' ).exists() ).toBe( true );
		expect( radio( editor, 'Subject-first' ).exists() ).toBe( true );
		expect( settingLabelled( editor, 'Wiki type' ).text() ).toContain( 'Each new Subject gets its own page.' );
	} );

	it( 'shows a setting the page does not set with the value in effect, with no way back to the default', () => {
		const editor = newEditor( [ SUBJECT_FIRST, DATA_LANGUAGES ] );

		expect( radio( editor, 'Page-first' ).element.checked ).toBe( true );
		expect( listedLanguages( editor ) ).toEqual( [ 'German' ] );
		expect( resetLink( editor, 'Wiki type' ).exists() ).toBe( false );
		expect( resetLink( editor, 'Data languages' ).exists() ).toBe( false );
	} );

	it( 'shows a setting the page sets with the value it sets, with a way back to the default', () => {
		const editor = newEditor( [ AUTO_RENDER ] );

		expect( checkbox( editor, 'Show Main Subjects' ).element.checked ).toBe( false );
		expect( resetLink( editor, 'Show Main Subjects' ).exists() ).toBe( true );
	} );

	it( 'names the setting its reset link is for, for a screen reader', () => {
		const editor = newEditor( [ AUTO_RENDER ] );

		expect( resetLink( editor, 'Show Main Subjects' ).attributes( 'aria-label' ) )
			.toBe( 'Reset Show Main Subjects as infoboxes to default' );
	} );

	it( 'returns a setting to its default', async () => {
		const editor = newEditor( [ AUTO_RENDER ] );

		await resetLink( editor, 'Show Main Subjects' ).trigger( 'click' );

		expect( checkbox( editor, 'Show Main Subjects' ).element.checked ).toBe( true );
		expect( resetLink( editor, 'Show Main Subjects' ).exists() ).toBe( false );
	} );

	it( 'moves the focus to the value in effect of a choice it returns to its default', async () => {
		const editor = newEditor( [ { ...SUBJECT_FIRST, localSettingsValue: true, pageValue: false } ] );

		await resetLink( editor, 'Wiki type' ).trigger( 'click' );
		await editor.vm.$nextTick();

		expect( document.activeElement ).toBe( radio( editor, 'Subject-first' ).element );
	} );

	it( 'moves the focus to the setting it returns to its default', async () => {
		const editor = newEditor( [ AUTO_RENDER ] );

		await resetLink( editor, 'Show Main Subjects' ).trigger( 'click' );
		await editor.vm.$nextTick();

		expect( document.activeElement ).toBe( checkbox( editor, 'Show Main Subjects' ).element );
	} );

	it( 'moves the focus to the language list it returns to its default', async () => {
		const editor = newEditor( [ { ...DATA_LANGUAGES, pageValue: [ 'fr' ] } ] );

		await resetLink( editor, 'Data languages' ).trigger( 'click' );
		await editor.vm.$nextTick();

		expect( document.activeElement ).toBe( editor.find( '.ext-neowiki-language-list-input' ).element );
	} );

	it( 'offers no save until a setting changes', async () => {
		const editor = newEditor( [ AUTO_RENDER ] );

		expect( saveButton( editor ).element.disabled ).toBe( true );

		await checkbox( editor, 'Show Main Subjects' ).setValue( true );

		expect( saveButton( editor ).element.disabled ).toBe( false );
	} );

	it( 'offers no save once a change is undone', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ] );

		await radio( editor, 'Subject-first' ).setValue();
		await resetLink( editor, 'Wiki type' ).trigger( 'click' );

		expect( saveButton( editor ).element.disabled ).toBe( true );
	} );

	it( 'saves the settings the page sets and no others', async () => {
		const pageSaver = new SpyPageSaver();
		const editor = newEditor( [ SUBJECT_FIRST, AUTO_RENDER, DATA_LANGUAGES ], { pageSaver } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( savedJson( pageSaver ) ).toEqual( { subjectFirst: true, autoRenderMainSubject: false } );
	} );

	it( 'saves without a setting returned to its default', async () => {
		const pageSaver = new SpyPageSaver();
		const editor = newEditor( [ SUBJECT_FIRST, AUTO_RENDER ], { pageSaver } );

		await resetLink( editor, 'Show Main Subjects' ).trigger( 'click' );
		await save( editor );

		expect( savedJson( pageSaver ) ).toEqual( {} );
	} );

	it( 'saves the configuration page as JSON with the edit summary', async () => {
		const pageSaver = new SpyPageSaver();
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver } );

		await radio( editor, 'Subject-first' ).setValue();
		await summaryInput( editor ).setValue( 'Turn subject-first on' );
		await save( editor );

		expect( pageSaver.saves[ 0 ] ).toMatchObject( {
			pageName: 'MediaWiki:NeoWiki',
			comment: 'Turn subject-first on',
			contentModel: 'json',
		} );
	} );

	// Null is a page that does not exist yet, which the save creates.
	it.each( [ 42, null ] )( 'saves over the revision it started from: %s', async ( revisionId ) => {
		const pageSaver = new SpyPageSaver();
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver, revisionId } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( pageSaver.saves[ 0 ].baseRevisionId ).toBe( revisionId );
	} );

	it( 'offers no second save while one is running', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver: new StalledPageSaver() } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( saveButton( editor ).element.disabled ).toBe( true );
	} );

	it( 'confirms a save', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ] );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( mw.notify ).toHaveBeenCalledWith( 'Settings saved.', { type: 'success' } );
	} );

	it( 'starts the next edit summary empty once saved', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ] );

		await radio( editor, 'Subject-first' ).setValue();
		await summaryInput( editor ).setValue( 'Turn subject-first on' );
		await save( editor );

		expect( summaryInput( editor ).element.value ).toBe( '' );
	} );

	it( 'shows the saved settings once saved, offering no save until the next change', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ] );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( radio( editor, 'Subject-first' ).element.checked ).toBe( true );
		expect( resetLink( editor, 'Wiki type' ).exists() ).toBe( true );
		expect( saveButton( editor ).element.disabled ).toBe( true );
	} );

	it( 'saves the next change over the revision the last save made', async () => {
		const pageSaver = new SpyPageSaver( [ { success: true, revisionId: 57 } ] );
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );
		await radio( editor, 'Page-first' ).setValue();
		await save( editor );

		expect( pageSaver.saves[ 1 ].baseRevisionId ).toBe( 57 );
	} );

	// MediaWiki merges a change saved since the form loaded into the save where it can.
	const SAVED_WITH_A_CHANGE_MADE_MEANWHILE: SaveOutcome = {
		success: true,
		revisionId: 57,
		source: '{ "subjectFirst": true, "autoRenderMainSubject": false }',
	};

	it( 'says when the page saved holds a change made meanwhile, which the form does not show', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver: new SpyPageSaver( [ SAVED_WITH_A_CHANGE_MADE_MEANWHILE ] ) } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( editor.find( '.cdx-message--warning' ).text() ).toBe( 'This page was also changed after the form was loaded.' );
	} );

	it( 'offers no further save once the page saved holds a change made meanwhile', async () => {
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver: new SpyPageSaver( [ SAVED_WITH_A_CHANGE_MADE_MEANWHILE ] ) } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );
		await radio( editor, 'Page-first' ).setValue();

		expect( saveButton( editor ).element.disabled ).toBe( true );
	} );

	it( 'shows why a save failed, keeping the changes to save again', async () => {
		const pageSaver = new SpyPageSaver( [ { success: false, message: 'Edit conflict.' } ] );
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );

		expect( editor.find( '.cdx-message--error' ).text() ).toBe( 'The settings were not saved: Edit conflict.' );
		expect( saveButton( editor ).element.disabled ).toBe( false );
	} );

	it( 'drops the reason a save failed once a save succeeds', async () => {
		const pageSaver = new SpyPageSaver( [ { success: false, message: 'Edit conflict.' } ] );
		const editor = newEditor( [ SUBJECT_FIRST ], { pageSaver } );

		await radio( editor, 'Subject-first' ).setValue();
		await save( editor );
		await save( editor );

		expect( editor.find( '.cdx-message--error' ).exists() ).toBe( false );
	} );

	it( 'shows the settings read-only to someone who may not change them', () => {
		const editor = newEditor( [ AUTO_RENDER, DATA_LANGUAGES ], { canEdit: false } );

		expect( editor.text() ).toContain( 'You do not have permission to change these settings.' );
		expect( checkbox( editor, 'Show Main Subjects' ).element.disabled ).toBe( true );
		expect( editor.findAll( 'button' ) ).toHaveLength( 0 );
	} );

} );
