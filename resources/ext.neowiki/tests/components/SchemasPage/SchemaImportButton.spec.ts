import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxButton } from '@wikimedia/codex';
import SchemaImportButton from '@/components/SchemasPage/SchemaImportButton.vue';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

let pinia: ReturnType<typeof createPinia>;

function summary( name: string ): SchemaSummary {
	return { name, description: '', propertyCount: 0 };
}

function listSchemas( ...names: string[] ): void {
	useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( names.map( summary ) );
}

function exportFile( ...names: string[] ): string {
	return JSON.stringify( {
		schemas: Object.fromEntries( names.map( ( name ) => [ name, { propertyDefinitions: {} } ] ) ),
	} );
}

function mountButton(): VueWrapper {
	return mount( SchemaImportButton, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
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

function checkedState( wrapper: VueWrapper ): [ string, boolean ][] {
	return wrapper.findAll( '.cdx-checkbox' ).map( ( checkbox ) => [
		checkbox.find( '.cdx-label__label__text' ).text(),
		( checkbox.find( 'input' ).element as HTMLInputElement ).checked,
	] );
}

describe( 'SchemaImportButton', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'notify' ] } );

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

	it( 'lists the Schemas of the chosen file, leaving the ones this wiki has unchecked', async () => {
		listSchemas( 'Company', 'Person' );
		const wrapper = mountButton();

		await chooseFile( wrapper, exportFile( 'Person', 'Museum' ) );

		expect( checkedState( wrapper ) ).toEqual( [ [ 'Person', false ], [ 'Museum', true ] ] );
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
