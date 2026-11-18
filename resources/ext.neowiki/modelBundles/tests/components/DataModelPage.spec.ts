import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import DataModelPage from '../../src/components/DataModelPage.vue';
import { ModelBundleService } from '../../src/ModelBundleServices.ts';
import type { InstallRecord } from '../../src/application/InstallRecord.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../../tests/VueTestHelpers.ts';
import { InMemoryDataModel } from '../application/InMemoryDataModel.ts';
import { installed, subjectEntry } from '../application/builders.ts';

const downloads = vi.hoisted( () => [] as { fileName: string; content: string }[] );

vi.mock( '@/presentation/downloadFile.ts', () => ( {
	downloadFile: ( fileName: string, content: string ) => downloads.push( { fileName, content } ),
} ) );

const reloadPage = vi.hoisted( () => vi.fn() );

// jsdom cannot reload a page.
vi.mock( '../../src/presentation/setupHistory.ts', async ( importOriginal ) => ( {
	...await importOriginal<object>(),
	reloadPage,
} ) );

enableAutoUnmount( afterEach );

const FEMALE_ID = 's1demo1aaaaaaa1';
const MALE_ID = 's1demo1aaaaaaa2';
const FEMALE = subjectEntry( { label: 'female' } );
const MALE = subjectEntry( { label: 'male' } );
const GENDER_TYPES = newSchema( { title: 'Gender Types' } );
const PLAIN_FILE = JSON.stringify( { schemas: { 'Gender Types': { propertyDefinitions: {} } } } );

let wiki: InMemoryDataModel;

function vocabularies( overrides: Partial<InstallRecord> = {} ): InstallRecord {
	return installed(
		[ [ FEMALE_ID, FEMALE ], [ MALE_ID, MALE ] ],
		[ GENDER_TYPES ],
		{ version: '1.0', description: 'Controlled lists', ...overrides },
	);
}

/**
 * What Special:DataModel tells the setup: of a wiki that has a data model, whose settings this user may change.
 */
const SETUP = {
	wikiHasSchemas: true,
	wikiHasSubjects: true,
	canEditSettings: true,
	settings: {
		revisionId: 7,
		pageValues: { subjectFirst: null, dataLanguages: null, enforceValidation: true, autoRenderMainSubject: null },
		localSettingsValues: { subjectFirst: false, dataLanguages: [], enforceValidation: false, autoRenderMainSubject: true },
	},
};

const savedPages = vi.hoisted( () => [] as { page: string; source: string }[] );

function mockMw( config: Record<string, unknown> = {} ): void {
	setupMwMock( {
		functions: [ 'msg', 'message', 'config', 'util', 'language' ],
		messages: { 'colon-separator': ': ', 'comma-separator': ', ' },
		config: { wgServerName: 'wiki.example.org', wgNeoWikiModelBundlesSetup: SETUP, ...config },
	} );
}

async function mountPage( mayInstall = true, view: string | null = null ): Promise<VueWrapper> {
	const wrapper = mount( DataModelPage, {
		props: { view },
		attachTo: document.body,
		global: {
			plugins: [ createPinia() ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ ModelBundleService.DataModelRepository ]: wiki,
				[ ModelBundleService.InstallRecordRepository ]: wiki,
				[ ModelBundleService.ModelBundlePermissionHints ]: { canInstallModelBundles: async () => mayInstall },
				[ ModelBundleService.PageSaver ]: {
					saveRevision: async ( page: string, source: string ) => {
						savedPages.push( { page, source } );
						return { success: true, revisionId: 8, source };
					},
				},
			},
			stubs: { CdxIcon: true },
		},
	} );
	await flushPromises();

	return wrapper;
}

/** The input of the checkbox whose label reads as given. */
function checkboxLabelled( wrapper: VueWrapper, label: string ): DOMWrapper<HTMLInputElement> {
	const checkbox = wrapper.findAll( '.cdx-checkbox' ).find( ( candidate ) => candidate.find( '.cdx-label__label__text' ).text() === label );

	expect( checkbox, `checkbox ${ label }` ).toBeDefined();
	return checkbox!.find<HTMLInputElement>( 'input' );
}

// jsdom has no file chooser, so the choice is put on the input as a browser would put it there.
async function chooseFile( wrapper: VueWrapper, content: string, fileName = 'schemas.json' ): Promise<void> {
	const input = wrapper.find<HTMLInputElement>( 'input[type="file"]' );
	Object.defineProperty( input.element, 'files', {
		value: [ new File( [ content ], fileName, { type: 'application/json' } ) ],
		configurable: true,
	} );
	await input.trigger( 'change' );
	await flushPromises();
}

function button( wrapper: VueWrapper, label: string ): DOMWrapper<HTMLButtonElement> {
	const found = wrapper.findAll<HTMLButtonElement>( 'button' ).find( ( candidate ) => candidate.text() === label );

	expect( found ).toBeDefined();
	return found!;
}

/**
 * For each bundle of the preview, what marks it as required by others, or null.
 */
function requiredMarks( wrapper: VueWrapper ): ( string | null )[] {
	return wrapper.findAll( '.ext-neowiki-bundle-plans__bundle' ).map( ( bundle ) => {
		const mark = bundle.find( '.ext-neowiki-bundle-plans__required' );
		return mark.exists() ? mark.text() : null;
	} );
}

function hasButton( wrapper: VueWrapper, label: string ): boolean {
	return wrapper.findAll( 'button' ).some( ( candidate ) => candidate.text() === label );
}

async function click( wrapper: VueWrapper, label: string ): Promise<void> {
	await button( wrapper, label ).trigger( 'click' );
	await flushPromises();
}

async function expand( wrapper: VueWrapper, group: string ): Promise<void> {
	await wrapper.find( `button[aria-label="neowiki-bundle-group-expand${ group }"]` ).trigger( 'click' );
	await flushPromises();
}

function groupHeaders( wrapper: VueWrapper ): string[][] {
	return wrapper.findAll( '.ext-neowiki-bundle-group__title' ).map(
		( title ) => title.findAll( '.ext-neowiki-bundle-group__name, .ext-neowiki-bundle-group__detail' ).map( ( part ) => part.text() ),
	);
}

/**
 * Each listed item: what it is called, and what the list says of it.
 */
function listedItems( wrapper: VueWrapper ): [ string, string | null ][] {
	return wrapper.findAll( '.ext-neowiki-item-list__items li' ).map( ( item ) => {
		const status = item.find( '.ext-neowiki-item-list__status' );
		return [ item.find( 'a, span' ).text(), status.exists() ? status.text() : null ];
	} );
}

function bundleFile( version: string, subjects: Record<string, unknown> ): string {
	return JSON.stringify( {
		bundle: { name: 'Vocabularies', version },
		schemas: { 'Gender Types': { description: 'TestSchema description', propertyDefinitions: {} } },
		subjects,
	} );
}

describe( 'DataModelPage', () => {

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	beforeEach( () => {
		mockMw();
		setActivePinia( createPinia() );
		downloads.length = 0;
		savedPages.length = 0;
		wiki = new InMemoryDataModel().withSchemas( GENDER_TYPES, newSchema( { title: 'Person' } ) );
		wiki.withSubject( FEMALE_ID, FEMALE ).withSubject( MALE_ID, subjectEntry( { label: 'male (edited)' } ) );
		wiki.layouts.set( 'Person card', { schema: 'Person', type: 'infobox' } );
		wiki.mappings.set( 'EDM', { version: 1, schemas: {} } );
		wiki.records = [ vocabularies() ];
	} );

	it( 'shows each installed model bundle with its version and its source', async () => {
		const wrapper = await mountPage();

		expect( groupHeaders( wrapper ) ).toEqual( [
			[ 'Vocabularies', 'neowiki-bundle-version1.0', 'neowiki-bundle-source-from-file', 'neowiki-bundle-record-link' ],
			[ 'neowiki-bundle-group-this-wiki' ],
		] );
	} );

	it( 'links each bundle to its install record page', async () => {
		const wrapper = await mountPage();

		const link = wrapper.findAll( '.ext-neowiki-bundle-group a' ).find( ( candidate ) => candidate.text() === 'neowiki-bundle-record-link' );

		expect( link?.attributes( 'href' ) ).toBe( '/wiki/MediaWiki:NeoWiki/Model bundles/Vocabularies.json' );
	} );

	it( 'tells how much this wiki changed of a bundle once its group is expanded', async () => {
		const wrapper = await mountPage();

		await expand( wrapper, 'Vocabularies' );

		expect( groupHeaders( wrapper )[ 0 ] ).toContain( 'neowiki-bundle-changed-count1' );
	} );

	it( 'lists the wiki\'s Schemas, Layouts and Mappings once for all groups', async () => {
		const listSchemas = vi.spyOn( wiki, 'getSchemaSummaries' );
		const wrapper = await mountPage();

		await expand( wrapper, 'Vocabularies' );
		await expand( wrapper, 'neowiki-bundle-group-this-wiki' );

		expect( listSchemas ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'lists what a bundle holds by type, Subjects by Schema, each with its status', async () => {
		const wrapper = await mountPage();

		await expand( wrapper, 'Vocabularies' );

		expect( wrapper.findAll( '.ext-neowiki-item-list__schema' ).map( ( schema ) => schema.text() ) ).toEqual( [ 'Gender Types' ] );
		expect( listedItems( wrapper ) ).toEqual( [
			[ 'Gender Types', 'neowiki-bundle-status-unchanged' ],
			[ 'female', 'neowiki-bundle-status-unchanged' ],
			[ 'male (edited)', 'neowiki-bundle-status-changed-here' ],
		] );
	} );

	it( 'lists a Subject this wiki deleted by its recorded label, under its recorded Schema', async () => {
		wiki.subjects.delete( MALE_ID );
		const wrapper = await mountPage();

		await expand( wrapper, 'Vocabularies' );

		expect( listedItems( wrapper ) ).toContainEqual( [ 'male', 'neowiki-bundle-status-deleted-here' ] );
		expect( wrapper.findAll( '.ext-neowiki-item-list__schema' ).map( ( schema ) => schema.text() ) ).toEqual( [ 'Gender Types' ] );
	} );

	it( 'lists, as this wiki\'s, the Schemas, Layouts and Mappings no bundle holds', async () => {
		const wrapper = await mountPage();

		await expand( wrapper, 'neowiki-bundle-group-this-wiki' );

		expect( listedItems( wrapper ) ).toEqual( [ [ 'Person', null ], [ 'Person card', null ], [ 'EDM', null ] ] );
	} );

	it( 'tells of another version at the URL a bundle was installed from, and updates from there', async () => {
		wiki.records = [ vocabularies( { source: 'https://example.org/vocabularies.json' } ) ];
		vi.stubGlobal( 'fetch', async () => new Response( bundleFile( '1.1', {} ) ) );
		const wrapper = await mountPage();

		expect( groupHeaders( wrapper )[ 0 ] ).toContain( 'neowiki-bundle-version-available1.1' );

		await click( wrapper, 'neowiki-bundle-update-from-source' );

		expect( wrapper.find( '.ext-neowiki-bundle-import__name' ).text() ).toContain( 'neowiki-bundle-version1.1' );
	} );

	it( 'links no source and offers no update from it for a record whose source is no web address', async () => {
		// eslint-disable-next-line no-script-url
		wiki.records = [ vocabularies( { source: 'javascript:alert( 1 )' } ) ];
		const fetched = vi.fn();
		vi.stubGlobal( 'fetch', fetched );
		const wrapper = await mountPage();

		// eslint-disable-next-line no-script-url
		expect( wrapper.findAll( '.ext-neowiki-bundle-group a' ).map( ( link ) => link.attributes( 'href' ) ) ).not.toContain( 'javascript:alert( 1 )' );
		expect( hasButton( wrapper, 'neowiki-bundle-update-from-source' ) ).toBe( false );
		expect( fetched ).not.toHaveBeenCalled();
	} );

	it( 'reads a bundle only from a web address', async () => {
		const fetched = vi.fn();
		vi.stubGlobal( 'fetch', fetched );
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await wrapper.find( 'input[type="url"]' ).setValue( 'data:application/json,{"schemas":{"A":{"propertyDefinitions":{}}}}' );
		await click( wrapper, 'neowiki-bundle-source-url-button' );

		expect( wrapper.find( '.cdx-message--error' ).text() ).toBe( 'neowiki-bundle-url-not-web' );
		expect( fetched ).not.toHaveBeenCalled();
	} );

	it( 'says when a URL cannot be read, pointing to the file instead', async () => {
		vi.stubGlobal( 'fetch', async () => {
			throw new TypeError( 'Failed to fetch' );
		} );
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await wrapper.find( 'input[type="url"]' ).setValue( 'https://example.org/vocabularies.json' );
		await click( wrapper, 'neowiki-bundle-source-url-button' );

		expect( wrapper.find( '.cdx-message--error' ).text() ).toBe( 'neowiki-bundle-url-unreachable' );
	} );

	it.each( [
		[ 'another bundle', { bundle: { name: 'Agents', version: '1.1' }, schemas: { Agent: { propertyDefinitions: {} } } } ],
		[ 'no version', { bundle: { name: 'Vocabularies' }, schemas: { 'Gender Types': { propertyDefinitions: {} } } } ],
	] )( 'tells of no other version when the file at the source has %s', async ( _case, file ) => {
		wiki.records = [ vocabularies( { source: 'https://example.org/vocabularies.json' } ) ];
		vi.stubGlobal( 'fetch', async () => new Response( JSON.stringify( file ) ) );

		const wrapper = await mountPage();

		expect( wrapper.find( '.ext-neowiki-bundle-group__detail--available' ).exists() ).toBe( false );
	} );

	it( 'checks a bundle\'s source for another version only for a user who may install', async () => {
		wiki.records = [ vocabularies( { source: 'https://example.org/vocabularies.json' } ) ];
		const fetched = vi.fn();
		vi.stubGlobal( 'fetch', fetched );

		await mountPage( false );

		expect( fetched ).not.toHaveBeenCalled();
	} );

	it( 'offers to install and update model bundles only to a user who may', async () => {
		const wrapper = await mountPage( false );

		expect( hasButton( wrapper, 'neowiki-bundle-install-title' ) ).toBe( false );
		expect( hasButton( wrapper, 'neowiki-bundle-update-from-file' ) ).toBe( false );
		expect( hasButton( wrapper, 'neowiki-bundle-export-title' ) ).toBe( true );
	} );

	it( 'installs a model bundle from a file and lists it afterwards', async () => {
		wiki.records = [];
		wiki.subjects.clear();
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await chooseFile( wrapper, bundleFile( '1.0', { [ FEMALE_ID ]: { label: 'female', schema: 'Gender Types', statements: {} } } ) );
		await click( wrapper, 'neowiki-bundle-install-button' );

		expect( wiki.writes ).toEqual( [ `create subject:${ FEMALE_ID }` ] );
		expect( wrapper.find( '.ext-neowiki-import-results .cdx-message--success' ).text() )
			.toBe( 'neowiki-bundle-installedVocabularies: neowiki-bundle-count-created1' );

		await click( wrapper, 'neowiki-bundle-back' );

		expect( groupHeaders( wrapper )[ 0 ][ 0 ] ).toBe( 'Vocabularies' );
	} );

	it( 'warns that an install wrote not every item, saying how many it did not', async () => {
		wiki.records = [];
		wiki.subjects.clear();
		wiki.failingWrites.set( `subject:${ FEMALE_ID }`, 'The page is protected' );
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await chooseFile( wrapper, bundleFile( '1.0', { [ FEMALE_ID ]: { label: 'female', schema: 'Gender Types', statements: {} } } ) );
		await click( wrapper, 'neowiki-bundle-install-button' );

		const warning = wrapper.find( '.ext-neowiki-import-results .cdx-message--warning' );
		expect( [ warning.text().includes( 'neowiki-bundle-installedVocabularies' ), warning.text().includes( 'neowiki-bundle-not-written1' ) ] )
			.toEqual( [ true, true ] );
	} );

	it( 'tells, after what it wrote, that it could not record the bundle as installed', async () => {
		wiki.records = [];
		wiki.failingWrites.set( 'record', 'Edit conflict' );
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await chooseFile( wrapper, bundleFile( '1.0', {} ) );
		await click( wrapper, 'neowiki-bundle-install-button' );

		expect( wrapper.find( '.ext-neowiki-import-results .cdx-message--error' ).text() )
			.toBe( 'neowiki-bundle-record-failedVocabulariesEdit conflict' );
		expect( wrapper.find( '.ext-neowiki-import-results .cdx-message--success' ).exists() ).toBe( false );
	} );

	it( 'heads the view as an update when the file chosen to install holds an installed bundle', async () => {
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await chooseFile( wrapper, bundleFile( '1.1', {} ) );

		expect( wrapper.find( '.ext-neowiki-bundle-import__title' ).text() ).toBe( 'neowiki-bundle-update-titleVocabularies' );
	} );

	it( 'names the bundle of a file that names none after the file', async () => {
		wiki.records = [];
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await chooseFile( wrapper, PLAIN_FILE, 'gender-types.json' );

		expect( ( wrapper.find( '.ext-neowiki-bundle-import__summary input' ).element as HTMLInputElement ).value ).toBe( 'gender-types' );
	} );

	it( 'says when the installed version of a bundle it updates has no version', async () => {
		wiki.records = [ vocabularies( { version: '' } ) ];
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-update-from-file' );
		await chooseFile( wrapper, bundleFile( '2.0', {} ) );

		expect( wrapper.find( '.ext-neowiki-bundle-import__summary' ).text() ).toContain( 'neowiki-bundle-updates-unversioned' );
	} );

	it( 'updates the bundle from a file that names none', async () => {
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-update-from-file' );
		await chooseFile( wrapper, PLAIN_FILE );

		expect( hasButton( wrapper, 'neowiki-bundle-update-button' ) ).toBe( true );
	} );

	describe( 'from a catalog', () => {

		const CATALOG_URL = 'https://example.org/catalog.json';

		function serve( files: Record<string, unknown> ): void {
			vi.stubGlobal( 'fetch', async ( url: string ) => url in files ?
				new Response( JSON.stringify( files[ url ] ) ) :
				new Response( 'Not found', { status: 404 } ) );
		}

		/**
		 * Each kind's heading, with the name, installed version and description of each of its entries.
		 */
		function catalogEntries( wrapper: VueWrapper ): string[][] {
			return wrapper.findAll( '.ext-neowiki-catalog-entries__group' ).map( ( group ) => [
				group.find( 'h3' ).text(),
				...group.findAll( '.ext-neowiki-catalog-entries__text' ).map( ( text ) => text.findAll( 'strong, span, p' )
					.map( ( part ) => part.text() ).join( ' / ' ) ),
			] );
		}

		beforeEach( () => {
			mockMw( { wgNeoWikiModelBundleCatalogs: [ CATALOG_URL ] } );
			serve( {
				[ CATALOG_URL ]: { bundles: [
					{ url: 'https://example.org/edm.json', name: 'EDM', description: 'Exports as EDM', kind: 'destination', requires: [ 'Agents' ] },
					{ url: 'https://example.org/agents.json', name: 'Agents', description: 'People and organisations' },
					{ url: 'https://example.org/vocabularies.json', name: 'Vocabularies' },
				] },
				'https://example.org/agents.json': { bundle: { name: 'Agents', version: '1' }, schemas: { Agent: { propertyDefinitions: {} } } },
				'https://example.org/edm.json': { bundle: { name: 'EDM', version: '3' }, mappings: { EDM: { version: 1, schemas: {} } } },
			} );
		} );

		it( 'lists the bundles of the catalogs by kind, saying which are installed', async () => {
			const wrapper = await mountPage();

			await click( wrapper, 'neowiki-bundle-install-title' );

			expect( catalogEntries( wrapper ) ).toEqual( [
				[ 'neowiki-bundle-catalog-models', 'Agents / People and organisations', 'Vocabularies / neowiki-bundle-catalog-installed-version1.0' ],
				[ 'neowiki-bundle-catalog-destinations', 'EDM / Exports as EDM' ],
			] );
		} );

		it( 'installs a bundle with the bundles it requires, each with its own install record', async () => {
			wiki.mappings.clear();
			const wrapper = await mountPage();

			await click( wrapper, 'neowiki-bundle-install-title' );
			await wrapper.find( 'button[aria-label="neowiki-bundle-catalog-loadEDM"]' ).trigger( 'click' );
			await flushPromises();
			await click( wrapper, 'neowiki-bundle-install-button' );

			expect( wiki.writes ).toEqual( [ 'save schema:Agent', 'save mapping:EDM' ] );
			expect( [ wiki.recordOf( 'Agents' )?.source, wiki.recordOf( 'EDM' )?.source ] )
				.toEqual( [ 'https://example.org/agents.json', 'https://example.org/edm.json' ] );
		} );

		it( 'marks a bundle as required by the one chosen', async () => {
			wiki.mappings.clear();
			const wrapper = await mountPage();

			await click( wrapper, 'neowiki-bundle-install-title' );
			await wrapper.find( 'button[aria-label="neowiki-bundle-catalog-loadEDM"]' ).trigger( 'click' );
			await flushPromises();

			expect( requiredMarks( wrapper ) ).toEqual( [ 'neowiki-bundle-required-byEDM', null ] );
		} );

	} );

	describe( 'setting up the data model', () => {

		const CATALOG_URL = 'https://example.org/catalog.json';
		const FILES: Record<string, unknown> = {
			[ CATALOG_URL ]: { bundles: [
				{ url: 'https://example.org/core.json', name: 'Core', description: 'Agents and places' },
				{ url: 'https://example.org/museum.json', name: 'Museum', description: 'Objects in collections', requires: [ 'Core' ] },
				{ url: 'https://example.org/edm.json', name: 'EDM', description: 'Exports as EDM', kind: 'destination', requires: [ 'Core' ] },
				{ url: 'https://example.org/bibframe.json', name: 'BIBFRAME', kind: 'destination', requires: [ 'Library' ] },
			] },
			'https://example.org/core.json': { bundle: { name: 'Core', version: '1' }, schemas: { Agent: { propertyDefinitions: {} } } },
			'https://example.org/museum.json': { bundle: { name: 'Museum', version: '1' }, schemas: { Artwork: { propertyDefinitions: {} } } },
			'https://example.org/edm.json': { bundle: { name: 'EDM', version: '1' }, mappings: { EDM: { version: 1, schemas: {} } } },
		};

		// What the browser keeps of the page for a reload, as its session storage keeps it.
		const sessionStore = new Map<string, string>();

		function setUp( setup: Partial<typeof SETUP> = {} ): void {
			mockMw( {
				wgNeoWikiModelBundleCatalogs: [ CATALOG_URL ],
				wgNeoWikiModelBundlePreselection: [ 'Museum', 'EDM' ],
				wgNeoWikiModelBundlesSetup: { ...SETUP, wikiHasSchemas: false, ...setup },
			} );
			Object.assign( mw, { storage: { session: {
				getObject: ( key: string ) => JSON.parse( sessionStore.get( key ) ?? 'null' ),
				setObject: ( key: string, value: unknown ) => sessionStore.set( key, JSON.stringify( value ) ).has( key ),
			} } } );
		}

		/**
		 * Each option of the step: its label, and whether it is chosen.
		 */
		function options( wrapper: VueWrapper ): [ string, boolean ][] {
			return wrapper.findAll( '.ext-neowiki-data-model-setup__option' ).map( ( option ) => [
				option.find( '.cdx-label__label__text' ).text(),
				option.find<HTMLInputElement>( 'input' ).element.checked,
			] );
		}

		async function chooseModel( wrapper: VueWrapper, label: string ): Promise<void> {
			const option = wrapper.findAll( '.ext-neowiki-data-model-setup__option' )
				.find( ( candidate ) => candidate.find( '.cdx-label__label__text' ).text() === label );

			expect( option, `option ${ label }` ).toBeDefined();
			await option!.find( 'input' ).setValue( true );
		}

		function shownStep( wrapper: VueWrapper ): string {
			return wrapper.find( 'h2' ).text();
		}

		/**
		 * The steps the step bar names, the current one in brackets.
		 */
		function stepBar( wrapper: VueWrapper ): string[] {
			return wrapper.findAll( '.ext-neowiki-setup-steps__step' ).map( ( step ) => {
				const label = step.find( '.ext-neowiki-setup-steps__label' ).text();
				return step.find( '[aria-current="step"]' ).exists() ? `[${ label }]` : label;
			} );
		}

		function stepButton( wrapper: VueWrapper, label: string ): DOMWrapper<HTMLButtonElement> {
			const found = wrapper.findAll<HTMLButtonElement>( '.ext-neowiki-setup-steps button' )
				.find( ( candidate ) => candidate.find( '.ext-neowiki-setup-steps__label' ).text() === label );

			expect( found, `step ${ label }` ).toBeDefined();
			return found!;
		}

		function plannedBundles( wrapper: VueWrapper ): string[] {
			return wrapper.findAll( '.ext-neowiki-bundle-plans__summary strong' ).map( ( name ) => name.text() );
		}

		function cancelLink( wrapper: VueWrapper ): DOMWrapper<Element> {
			const found = wrapper.findAll( 'a' ).find( ( link ) => link.text() === 'cancel' );

			expect( found ).toBeDefined();
			return found!;
		}

		/**
		 * The links that end the setup: their labels and targets, and whether each is the primary one.
		 */
		function endLinks( wrapper: VueWrapper ): [ string, string | undefined, boolean ][] {
			return wrapper.findAll( '.ext-neowiki-data-model-setup__actions a' ).map( ( link ) => [
				link.text(),
				link.attributes( 'href' ),
				link.classes( 'cdx-button--weight-primary' ),
			] );
		}

		function holdInstall(): void {
			vi.spyOn( wiki, 'saveSchema' ).mockImplementationOnce( () => new Promise( () => {
				// The first write never ends, which holds the install.
			} ) );
		}

		/**
		 * Moves through the history as the browser does, and waits for the page to follow.
		 */
		async function traverse( move: () => unknown ): Promise<void> {
			const popped = new Promise( ( resolve ) => {
				window.addEventListener( 'popstate', resolve, { once: true } );
			} );
			move();
			await popped;
			await flushPromises();
		}

		beforeEach( () => {
			wiki.records = [];
			wiki.mappings.clear();
			sessionStore.clear();
			reloadPage.mockClear();
			history.replaceState( null, '', '/wiki/Special:DataModel/setup' );
			setUp();
			vi.stubGlobal( 'fetch', async ( url: string ) => url in FILES ?
				new Response( JSON.stringify( FILES[ url ] ) ) :
				new Response( 'Not found', { status: 404 } ) );
		} );

		it( 'offers it on a wiki without a data model', async () => {
			const wrapper = await mountPage();

			expect( wrapper.find( '.ext-neowiki-data-model__setup a' ).attributes( 'href' ) ).toBe( '/wiki/Special:DataModel/setup' );
		} );

		it( 'opens it on its subpage', async () => {
			const wrapper = await mountPage( true, 'setup' );

			expect( wrapper.find( '.ext-neowiki-data-model-setup' ).exists() ).toBe( true );
		} );

		it( 'offers the models of the catalogs and starting empty, the preselected model chosen', async () => {
			const wrapper = await mountPage( true, 'setup' );

			expect( options( wrapper ) ).toEqual( [ [ 'Core', false ], [ 'Museum', true ], [ 'neowiki-bundle-setup-empty', false ] ] );
		} );

		it( 'offers the destinations the chosen model serves, the preselected ones ticked', async () => {
			const wrapper = await mountPage( true, 'setup' );

			await click( wrapper, 'neowiki-bundle-setup-continue' );

			expect( options( wrapper ) ).toEqual( [ [ 'EDM', true ] ] );
		} );

		it( 'installs the chosen model and destinations, with what they require, each with its own record', async () => {
			const wrapper = await mountPage( true, 'setup' );

			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-install-button' );

			expect( wiki.writes ).toEqual( [ 'save schema:Agent', 'save schema:Artwork', 'save mapping:EDM' ] );
			expect( wiki.records.map( ( record ) => record.name ).sort() ).toEqual( [ 'Core', 'EDM', 'Museum' ] );
			expect( wrapper.find( 'a[href="/wiki/Special:CreateSubject"]' ).exists() ).toBe( true );
		} );

		it( 'leads on from the end to creating a subject, or to the data model', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );

			await click( wrapper, 'neowiki-bundle-install-button' );

			expect( endLinks( wrapper ) ).toEqual( [
				[ 'neowiki-bundle-setup-create-subject', '/wiki/Special:CreateSubject', true ],
				[ 'neowiki-bundle-setup-open-data-model', '/wiki/Special:DataModel', false ],
			] );
		} );

		it( 'installs nothing when starting empty, and says so', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await chooseModel( wrapper, 'neowiki-bundle-setup-empty' );

			await click( wrapper, 'neowiki-bundle-setup-continue' );

			expect( [ wiki.records, wrapper.find( '.ext-neowiki-data-model-setup' ).text() ] )
				.toEqual( [ [], expect.stringContaining( 'neowiki-bundle-setup-nothing-installed' ) ] );
		} );

		it( 'says nothing was installed only when it was', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );

			await click( wrapper, 'neowiki-bundle-install-button' );

			expect( wrapper.text() ).not.toContain( 'neowiki-bundle-setup-nothing-installed' );
		} );

		it( 'leads on from starting empty to creating a schema, or to the data model', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await chooseModel( wrapper, 'neowiki-bundle-setup-empty' );

			await click( wrapper, 'neowiki-bundle-setup-continue' );

			expect( endLinks( wrapper ) ).toEqual( [
				[ 'neowiki-bundle-setup-create-schema', '/wiki/Special:Schemas', true ],
				[ 'neowiki-bundle-setup-open-data-model', '/wiki/Special:DataModel', false ],
			] );
		} );

		it( 'asks how the wiki works first on a wiki without Subjects', async () => {
			setUp( { wikiHasSubjects: false } );

			const wrapper = await mountPage( true, 'setup' );

			expect( wrapper.find( '.ext-neowiki-wiki-settings' ).exists() ).toBe( true );
		} );

		it( 'leads back to the data model from the first step too', async () => {
			setUp( { wikiHasSubjects: false } );

			const wrapper = await mountPage( true, 'setup' );

			expect( cancelLink( wrapper ).attributes( 'href' ) ).toBe( '/wiki/Special:DataModel' );
		} );

		it( 'names the steps the choices lead through, marking the current one', async () => {
			setUp( { wikiHasSubjects: false } );

			const wrapper = await mountPage( true, 'setup' );

			expect( stepBar( wrapper ) ).toEqual( [
				'[neowiki-bundle-setup-settings]',
				'neowiki-bundle-setup-step-model',
				'neowiki-bundle-setup-step-destinations',
				'neowiki-bundle-setup-review',
			] );
		} );

		it( 'names no step after the model for starting empty', async () => {
			const wrapper = await mountPage( true, 'setup' );

			await chooseModel( wrapper, 'neowiki-bundle-setup-empty' );

			expect( stepBar( wrapper ) ).toEqual( [ '[neowiki-bundle-setup-step-model]' ] );
		} );

		it( 'returns to a completed step from the step bar, the choices kept', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await chooseModel( wrapper, 'Core' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );

			await stepButton( wrapper, 'neowiki-bundle-setup-step-model' ).trigger( 'click' );
			await flushPromises();

			expect( [ shownStep( wrapper ), options( wrapper ) ] ).toEqual( [
				'neowiki-bundle-setup-model',
				[ [ 'Core', true ], [ 'Museum', false ], [ 'neowiki-bundle-setup-empty', false ] ],
			] );
		} );

		it( 'returns to the step before on the browser\'s Back, the choices kept', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await checkboxLabelled( wrapper, 'EDM' ).setValue( false );
			await click( wrapper, 'neowiki-bundle-setup-continue' );

			await traverse( () => history.back() );

			expect( [ shownStep( wrapper ), options( wrapper ) ] ).toEqual( [ 'neowiki-bundle-setup-destinations', [ [ 'EDM', false ] ] ] );
		} );

		it( 'returns to the review on the browser\'s Forward as it was left', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await wrapper.find( '.ext-neowiki-bundle-plans__toggle' ).trigger( 'click' );
			await checkboxLabelled( wrapper, 'Agent' ).setValue( false );

			await traverse( () => history.back() );
			await traverse( () => history.forward() );
			await wrapper.find( '.ext-neowiki-bundle-plans__toggle' ).trigger( 'click' );

			expect( [ shownStep( wrapper ), checkboxLabelled( wrapper, 'Agent' ).element.checked ] ).toEqual( [ 'neowiki-bundle-setup-review', false ] );
		} );

		it( 'marks the bundles that the chosen ones require', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );

			await click( wrapper, 'neowiki-bundle-setup-continue' );

			expect( requiredMarks( wrapper ) ).toEqual( [ 'neowiki-bundle-required-byMuseum and EDM', null, null ] );
		} );

		it( 'plans the review anew for the choices changed since', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await traverse( () => history.back() );
			await checkboxLabelled( wrapper, 'EDM' ).setValue( false );

			await click( wrapper, 'neowiki-bundle-setup-continue' );

			expect( plannedBundles( wrapper ) ).toEqual( [ 'Core', 'Museum' ] );
		} );

		it( 'goes back on the Back button as on the browser\'s, so that Forward returns', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );

			await traverse( () => button( wrapper, 'neowiki-bundle-setup-back' ).trigger( 'click' ) );
			await traverse( () => history.forward() );

			expect( shownStep( wrapper ) ).toBe( 'neowiki-bundle-setup-destinations' );
		} );

		it( 'shows the first step for a step the choices no longer lead to', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await traverse( () => history.back() );
			await chooseModel( wrapper, 'neowiki-bundle-setup-empty' );

			await traverse( () => history.forward() );

			expect( shownStep( wrapper ) ).toBe( 'neowiki-bundle-setup-model' );
		} );

		it( 'keeps the step and the choices through a reload', async () => {
			const before = await mountPage( true, 'setup' );
			await chooseModel( before, 'Core' );
			await click( before, 'neowiki-bundle-setup-continue' );
			await checkboxLabelled( before, 'EDM' ).setValue( false );
			before.unmount();

			const after = await mountPage( true, 'setup' );

			expect( [ shownStep( after ), options( after ) ] ).toEqual( [ 'neowiki-bundle-setup-destinations', [ [ 'EDM', false ] ] ] );
			await click( after, 'neowiki-bundle-setup-continue' );
			expect( plannedBundles( after ) ).toEqual( [ 'Core' ] );
		} );

		it( 'shows the step a reload left while it reads the catalogs', async () => {
			setUp( { wikiHasSubjects: false } );
			const before = await mountPage( true, 'setup' );
			await click( before, 'neowiki-bundle-setup-continue' );
			before.unmount();
			vi.stubGlobal( 'fetch', () => new Promise( () => {
				// The catalogs are still being read.
			} ) );

			const after = await mountPage( true, 'setup' );

			expect( shownStep( after ) ).toBe( 'neowiki-bundle-setup-model' );
		} );

		it( 'shows the first step rather than the end while it reads the catalogs after an install', async () => {
			const before = await mountPage( true, 'setup' );
			await click( before, 'neowiki-bundle-setup-continue' );
			await click( before, 'neowiki-bundle-setup-continue' );
			await click( before, 'neowiki-bundle-install-button' );
			before.unmount();
			vi.stubGlobal( 'fetch', () => new Promise( () => {
				// The catalogs are still being read.
			} ) );

			const after = await mountPage( true, 'setup' );

			expect( shownStep( after ) ).toBe( 'neowiki-bundle-setup-model' );
		} );

		it( 'starts afresh when opened anew', async () => {
			const before = await mountPage( true, 'setup' );
			await chooseModel( before, 'Core' );
			before.unmount();
			history.replaceState( null, '', '/wiki/Special:DataModel/setup' );

			const after = await mountPage( true, 'setup' );

			expect( options( after ) ).toEqual( [ [ 'Core', false ], [ 'Museum', true ], [ 'neowiki-bundle-setup-empty', false ] ] );
		} );

		it( 'reloads at the step before the review on the browser\'s Back once installed', async () => {
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-install-button' );

			await traverse( () => history.back() );

			expect( [ reloadPage.mock.calls.length, location.search ] ).toEqual( [ 1, '?step=destinations' ] );
		} );

		it( 'stays on the review while installing, whatever the browser\'s Back', async () => {
			holdInstall();
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-install-button' );

			await traverse( () => history.back() );

			expect( shownStep( wrapper ) ).toBe( 'neowiki-bundle-setup-review' );
		} );

		it( 'offers no way out of the review while installing', async () => {
			holdInstall();
			const wrapper = await mountPage( true, 'setup' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-setup-continue' );
			await click( wrapper, 'neowiki-bundle-install-button' );

			expect( [ button( wrapper, 'neowiki-bundle-setup-back' ).element.disabled, cancelLink( wrapper ).attributes( 'href' ) ] )
				.toEqual( [ true, undefined ] );
		} );

		it( 'leads back to the data model on cancelling', async () => {
			const wrapper = await mountPage( true, 'setup' );

			expect( cancelLink( wrapper ).attributes( 'href' ) ).toBe( '/wiki/Special:DataModel' );
		} );

		it( 'asks how the wiki works before the first install from a file or URL too', async () => {
			setUp( { wikiHasSubjects: false } );
			const wrapper = await mountPage();

			await click( wrapper, 'neowiki-bundle-install-title' );

			expect( wrapper.find( '.ext-neowiki-wiki-settings' ).exists() ).toBe( true );
		} );

		it( 'heads how the wiki works, before the first install from a file or URL, as the install', async () => {
			setUp( { wikiHasSubjects: false } );
			const wrapper = await mountPage();

			await click( wrapper, 'neowiki-bundle-install-title' );

			expect( wrapper.find( 'h2' ).text() ).toBe( 'neowiki-bundle-install-title' );
		} );

		it( 'returns to the data model from how the wiki works, before the first install from a file or URL', async () => {
			setUp( { wikiHasSubjects: false } );
			const wrapper = await mountPage();
			await click( wrapper, 'neowiki-bundle-install-title' );

			await click( wrapper, 'cancel' );

			expect( wrapper.find( '.ext-neowiki-data-model__intro' ).exists() ).toBe( true );
		} );

	} );

	it( 'records the URL a bundle was installed from', async () => {
		wiki.records = [];
		vi.stubGlobal( 'fetch', async () => new Response( bundleFile( '1.0', {} ) ) );
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-install-title' );
		await wrapper.find( 'input[type="url"]' ).setValue( 'https://example.org/vocabularies.json' );
		await click( wrapper, 'neowiki-bundle-source-url-button' );
		await click( wrapper, 'neowiki-bundle-install-button' );

		expect( wiki.recordOf( 'Vocabularies' )?.source ).toBe( 'https://example.org/vocabularies.json' );
	} );

	it( 'refuses to update a bundle from a file that holds another', async () => {
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-update-from-file' );
		await chooseFile( wrapper, JSON.stringify( { bundle: { name: 'Agents' }, schemas: { Agent: { propertyDefinitions: {} } } } ) );

		expect( wrapper.find( '.cdx-message--error' ).text() ).toBe( 'neowiki-bundle-other-bundleAgentsVocabularies' );
	} );

	it( 'exports the chosen Schemas as a model bundle named as the user names it', async () => {
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-export-title' );
		await wrapper.find( '.ext-neowiki-bundle-export input[type="checkbox"]' ).setValue( false );
		await wrapper.findAll<HTMLInputElement>( '.ext-neowiki-bundle-export__schema input[type="checkbox"]' )[ 0 ].setValue( true );
		const textInputs = wrapper.findAll<HTMLInputElement>( '.ext-neowiki-bundle-export .cdx-text-input__input' );
		await textInputs[ 0 ].setValue( 'Vocabularies' );
		await textInputs[ 1 ].setValue( '2.0' );
		await click( wrapper, 'neowiki-bundle-export-button' );

		expect( downloads.map( ( download ) => download.fileName ) ).toEqual( [ 'Vocabularies-2.0.json' ] );
		const exported = JSON.parse( downloads[ 0 ].content );
		expect( exported.bundle ).toEqual( { name: 'Vocabularies', version: '2.0' } );
		expect( Object.keys( exported.schemas ) ).toEqual( [ 'Gender Types' ] );
	} );

	it( 'exports a model bundle of Mappings alone', async () => {
		wiki.mappings.set( 'CIDOC-CRM', { version: 1, schemas: {} } );
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-export-title' );
		await checkboxLabelled( wrapper, 'neowiki-bundle-preview-all2' ).setValue( false );
		await checkboxLabelled( wrapper, 'CIDOC-CRM' ).setValue( false );
		await wrapper.findAll<HTMLInputElement>( '.ext-neowiki-bundle-export .cdx-text-input__input' )[ 0 ].setValue( 'ECHOLOT to EDM' );
		await click( wrapper, 'neowiki-bundle-export-button' );

		const exported = JSON.parse( downloads[ 0 ].content );
		expect( [ Object.keys( exported.schemas ), Object.keys( exported.mappings ) ] ).toEqual( [ [], [ 'EDM' ] ] );
	} );

	it( 'offers no export while no Schema and no Mapping is chosen', async () => {
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-export-title' );
		await checkboxLabelled( wrapper, 'neowiki-bundle-preview-all2' ).setValue( false );
		await checkboxLabelled( wrapper, 'neowiki-bundle-preview-all1' ).setValue( false );

		expect( button( wrapper, 'neowiki-bundle-export-button' ).element.disabled ).toBe( true );
	} );

	it( 'lets a user who may not install bundles name the export', async () => {
		const wrapper = await mountPage( false );

		await click( wrapper, 'neowiki-bundle-export-title' );
		await wrapper.findAll<HTMLInputElement>( '.ext-neowiki-bundle-export .cdx-text-input__input' )[ 0 ].setValue( 'Vocabularies' );
		await click( wrapper, 'neowiki-bundle-export-button' );

		expect( JSON.parse( downloads[ 0 ].content ).bundle ).toEqual( { name: 'Vocabularies' } );
	} );

	it( 'exports a plain export, named after the wiki, when the export is not named', async () => {
		const wrapper = await mountPage();

		await click( wrapper, 'neowiki-bundle-export-title' );
		await click( wrapper, 'neowiki-bundle-export-button' );

		expect( downloads.map( ( download ) => download.fileName ) ).toEqual( [ 'wiki.example.org-data-model.json' ] );
		expect( JSON.parse( downloads[ 0 ].content ) ).not.toHaveProperty( 'bundle' );
	} );

} );
