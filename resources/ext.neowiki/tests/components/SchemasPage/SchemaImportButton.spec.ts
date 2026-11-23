import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxButton } from '@wikimedia/codex';
import SchemaImportButton from '@/components/SchemasPage/SchemaImportButton.vue';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import type { SchemaImportItem } from '@/components/SchemasPage/schemaImport.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

let pinia: ReturnType<typeof createPinia>;

function summary( name: string ): SchemaSummary {
	return { name, description: '', propertyCount: 0, subjectsAreDependent: false, subjectsAreControlled: false };
}

function listSchemas( ...names: string[] ): void {
	useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( names.map( summary ) );
}

function exportFile( ...names: string[] ): string {
	return JSON.stringify( {
		schemas: Object.fromEntries( names.map( ( name ) => [ name, { propertyDefinitions: {} } ] ) ),
	} );
}

function mountButton( repository = new InMemorySchemaRepository( [] ) ): VueWrapper {
	return mount( SchemaImportButton, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: { [ Service.SchemaRepository ]: repository },
			stubs: { CdxIcon: true, teleport: true },
		},
	} );
}

function fileInput( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
	return wrapper.find<HTMLInputElement>( 'input[type="file"]' );
}

// jsdom has no file chooser, so the choice is put on the input as a browser would put it there.
async function chooseFile( wrapper: VueWrapper, content: string ): Promise<void> {
	Object.defineProperty( fileInput( wrapper ).element, 'files', {
		value: [ new File( [ content ], 'schemas.json', { type: 'application/json' } ) ],
		configurable: true,
	} );
	await fileInput( wrapper ).trigger( 'change' );
	await flushPromises();
}

function installModelBundles(): void {
	vi.mocked( mw.loader.getState ).mockImplementation(
		( module ) => module === 'ext.neowiki.modelBundles' ? 'registered' : null,
	);
}

function notification(): string | null {
	return ( vi.mocked( mw.notify ).mock.calls[ 0 ][ 0 ] as Element ).textContent;
}

function plannedImport( wrapper: VueWrapper ): [ string, string ][] {
	return ( wrapper.findComponent( SchemaImportDialog ).props( 'items' ) as SchemaImportItem[] )
		.map( ( item ) => [ item.schema.getName(), item.status ] );
}

describe( 'SchemaImportButton', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'message', 'notify', 'loader' ] } );

		pinia = createPinia();
		setActivePinia( pinia );
		listSchemas();
	} );

	it( 'opens a file chooser for JSON files', async () => {
		const wrapper = mountButton();
		const openChooser = vi.spyOn( fileInput( wrapper ).element, 'click' ).mockImplementation( () => undefined );

		await wrapper.findComponent( CdxButton ).trigger( 'click' );

		expect( openChooser ).toHaveBeenCalledTimes( 1 );
		expect( fileInput( wrapper ).attributes( 'accept' ) ).toContain( '.json' );
	} );

	it( 'offers the Schemas of the chosen file, compared with the wiki\'s', async () => {
		listSchemas( 'Company', 'Person' );
		const wrapper = mountButton( new InMemorySchemaRepository( [
			newSchema( { title: 'Company' } ),
			newSchema( { title: 'Person', description: 'A human being' } ),
		] ) );

		await chooseFile( wrapper, exportFile( 'Person', 'Museum' ) );

		expect( plannedImport( wrapper ) ).toEqual( [ [ 'Person', 'changed' ], [ 'Museum', 'new' ] ] );
	} );

	it( 'refers a file holding more than Schemas to the Data model page when Model Bundles is installed, importing nothing', async () => {
		installModelBundles();
		const wrapper = mountButton();

		await chooseFile( wrapper, JSON.stringify( {
			bundle: { name: 'Vocabularies' },
			schemas: { Museum: { propertyDefinitions: {} } },
		} ) );

		expect( notification() ).toBe( 'neowiki-schemas-import-model-bundle' );
		expect( wrapper.findComponent( SchemaImportDialog ).exists() ).toBe( false );
	} );

	it( 'offers only the Schemas of a file holding more, saying so, when Model Bundles is not installed', async () => {
		const wrapper = mountButton();

		await chooseFile( wrapper, JSON.stringify( {
			layouts: { 'Museum card': { schema: 'Museum', type: 'infobox' } },
			schemas: { Museum: { propertyDefinitions: {} } },
		} ) );

		expect( plannedImport( wrapper ) ).toEqual( [ [ 'Museum', 'new' ] ] );
		expect( wrapper.text() ).toContain( 'neowiki-schemas-import-schemas-only' );
	} );

	it( 'offers a file of Schemas only as before when Model Bundles is installed', async () => {
		installModelBundles();
		const wrapper = mountButton();

		await chooseFile( wrapper, exportFile( 'Museum' ) );

		expect( plannedImport( wrapper ) ).toEqual( [ [ 'Museum', 'new' ] ] );
		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-import-schemas-only' );
	} );

	it( 'reports a file it cannot import, and opens no dialog', async () => {
		const wrapper = mountButton();

		await chooseFile( wrapper, 'Person,Museum' );

		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-schemas-import-error-not-json',
			{ title: 'neowiki-schemas-import-error', type: 'error' },
		);
		expect( wrapper.findComponent( SchemaImportDialog ).exists() ).toBe( false );
	} );

	it( 'lets go of the chosen file, so choosing the same file again reads it again', async () => {
		const wrapper = mountButton();
		const setValue = vi.fn();
		Object.defineProperty( fileInput( wrapper ).element, 'value', { get: () => '', set: setValue, configurable: true } );

		await chooseFile( wrapper, exportFile( 'Museum' ) );

		expect( setValue ).toHaveBeenCalledWith( '' );
	} );

	it( 'closes the dialog when it asks to', async () => {
		const wrapper = mountButton();
		await chooseFile( wrapper, exportFile( 'Museum' ) );

		wrapper.findComponent( SchemaImportDialog ).vm.$emit( 'close' );
		await flushPromises();

		expect( wrapper.findComponent( SchemaImportDialog ).exists() ).toBe( false );
	} );

	it( 'tells the page once Schemas were imported', async () => {
		const wrapper = mountButton();
		await chooseFile( wrapper, exportFile( 'Museum' ) );

		wrapper.findComponent( SchemaImportDialog ).vm.$emit( 'imported' );

		expect( wrapper.emitted( 'imported' ) ).toHaveLength( 1 );
	} );
} );
