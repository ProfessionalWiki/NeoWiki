import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxButton } from '@wikimedia/codex';
import SchemaImportButton from '@/components/SchemasPage/SchemaImportButton.vue';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import type { ModelBundle } from '@/application/modelBundle/ModelBundle.ts';
import { Service } from '@/NeoWikiServices.ts';
import { chooseFile, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { InMemoryDataModel } from '../../application/modelBundle/InMemoryDataModel.ts';

enableAutoUnmount( afterEach );

let pinia: ReturnType<typeof createPinia>;

function exportFile( ...names: string[] ): string {
	return JSON.stringify( {
		schemas: Object.fromEntries( names.map( ( name ) => [ name, { propertyDefinitions: {} } ] ) ),
	} );
}

function bundleFile( name: string ): string {
	return JSON.stringify( { bundle: { name }, schemas: { Museum: { propertyDefinitions: {} } } } );
}

function mountButton( mayInstall = false ): VueWrapper {
	const wiki = new InMemoryDataModel();

	return mount( SchemaImportButton, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.DataModelRepository ]: wiki,
				[ Service.InstallRecordRepository ]: wiki,
				[ Service.ModelBundlePermissionHints ]: { canInstallModelBundles: async () => mayInstall },
			},
			stubs: { CdxIcon: true, SchemaImportDialog: true },
		},
	} );
}

function fileInput( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
	return wrapper.find<HTMLInputElement>( 'input[type="file"]' );
}

function offeredFile( wrapper: VueWrapper ): ModelBundle | undefined {
	const dialog = wrapper.findComponent( SchemaImportDialog );
	return dialog.exists() ? dialog.props( 'bundle' ) as ModelBundle : undefined;
}

describe( 'SchemaImportButton', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'notify' ] } );

		pinia = createPinia();
		setActivePinia( pinia );
	} );

	it( 'opens a file chooser for JSON files', async () => {
		const wrapper = mountButton();
		const openChooser = vi.spyOn( fileInput( wrapper ).element, 'click' ).mockImplementation( () => undefined );

		await wrapper.findComponent( CdxButton ).trigger( 'click' );

		expect( openChooser ).toHaveBeenCalledTimes( 1 );
		expect( fileInput( wrapper ).attributes( 'accept' ) ).toContain( '.json' );
	} );

	it( 'offers the import of what the chosen file holds', async () => {
		const wrapper = mountButton();

		await chooseFile( wrapper, exportFile( 'Person', 'Museum' ) );

		expect( [ ...offeredFile( wrapper )!.schemas.keys() ].sort() ).toEqual( [ 'Museum', 'Person' ] );
	} );

	it( 'reports a file it cannot import, and opens no dialog', async () => {
		const wrapper = mountButton();

		await chooseFile( wrapper, 'Person,Museum' );

		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-bundle-file-not-json',
			{ title: 'neowiki-schemas-import-error', type: 'error' },
		);
		expect( offeredFile( wrapper ) ).toBeUndefined();
	} );

	it( 'offers the install of a model bundle to a user who may install one', async () => {
		const wrapper = mountButton( true );

		await chooseFile( wrapper, bundleFile( 'Vocabularies' ) );

		expect( offeredFile( wrapper )?.metadata?.name ).toBe( 'Vocabularies' );
	} );

	it( 'refuses a model bundle to a user who may not install one', async () => {
		const wrapper = mountButton( false );

		await chooseFile( wrapper, bundleFile( 'Vocabularies' ) );

		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-bundle-install-not-allowed',
			{ title: 'neowiki-schemas-import-error', type: 'error' },
		);
		expect( offeredFile( wrapper ) ).toBeUndefined();
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

		expect( offeredFile( wrapper ) ).toBeUndefined();
	} );

	it( 'tells the page once something was imported', async () => {
		const wrapper = mountButton();
		await chooseFile( wrapper, exportFile( 'Museum' ) );

		wrapper.findComponent( SchemaImportDialog ).vm.$emit( 'imported' );

		expect( wrapper.emitted( 'imported' ) ).toHaveLength( 1 );
	} );
} );
