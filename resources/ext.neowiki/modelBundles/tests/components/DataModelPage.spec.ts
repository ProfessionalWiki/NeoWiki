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

async function mountPage( mayInstall = true ): Promise<VueWrapper> {
	const wrapper = mount( DataModelPage, {
		attachTo: document.body,
		global: {
			plugins: [ createPinia() ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ ModelBundleService.DataModelRepository ]: wiki,
				[ ModelBundleService.InstallRecordRepository ]: wiki,
				[ ModelBundleService.ModelBundlePermissionHints ]: { canInstallModelBundles: async () => mayInstall },
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
		setupMwMock( { functions: [ 'msg', 'message', 'config', 'util', 'language' ], config: { wgServerName: 'wiki.example.org' } } );
		setActivePinia( createPinia() );
		downloads.length = 0;
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
		expect( wrapper.find( '.ext-neowiki-import-results .cdx-message--success' ).text() ).toBe( 'neowiki-bundle-installedVocabularies' );

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
