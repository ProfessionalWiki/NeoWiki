import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxDialog } from '@wikimedia/codex';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import type { SchemaImportItem } from '@/components/SchemasPage/schemaImport.ts';
import type { Schema } from '@/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

let pinia: ReturnType<typeof createPinia>;

function item( name: string, replacesExisting = false ): SchemaImportItem {
	return { schema: newSchema( { title: name } ), replacesExisting };
}

function mountDialog( items: SchemaImportItem[] ): VueWrapper {
	return mount( SchemaImportDialog, {
		props: { items },
		attachTo: document.body,
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			stubs: { teleport: true },
		},
	} );
}

interface Row {
	name: string;
	checked: boolean;
	replaces: boolean;
}

function rows( wrapper: VueWrapper ): Row[] {
	return wrapper.findAll( '.cdx-checkbox' ).map( ( checkbox ) => ( {
		name: checkbox.find( '.cdx-label__label__text' ).text(),
		checked: ( checkbox.find( 'input' ).element as HTMLInputElement ).checked,
		replaces: checkbox.find( '.cdx-label__description' ).exists(),
	} ) );
}

function checkboxOf( wrapper: VueWrapper, name: string ): DOMWrapper<HTMLInputElement> {
	const checkbox = wrapper.findAll( '.cdx-checkbox' )
		.find( ( candidate ) => candidate.find( '.cdx-label__label__text' ).text() === name );

	expect( checkbox ).toBeDefined();
	return checkbox!.find<HTMLInputElement>( 'input' );
}

async function setChecked( wrapper: VueWrapper, name: string, checked: boolean ): Promise<void> {
	await checkboxOf( wrapper, name ).setValue( checked );
}

function primaryAction( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement> {
	return wrapper.find<HTMLButtonElement>( '.cdx-dialog__footer__primary-action' );
}

function defaultAction( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement> {
	return wrapper.find<HTMLButtonElement>( '.cdx-dialog__footer__default-action' );
}

async function runImport( wrapper: VueWrapper ): Promise<void> {
	await primaryAction( wrapper ).trigger( 'click' );
	await flushPromises();
}

function messages( wrapper: VueWrapper, type: 'success' | 'error' ): string[] {
	return wrapper.findAll( `.cdx-message--${ type }` ).map( ( message ) => message.text() );
}

function savedNames(): string[] {
	return vi.mocked( useSchemaStore().saveSchema ).mock.calls.map( ( [ schema ] ) => schema.getName() );
}

async function importWithSaveHeld(): Promise<{ wrapper: VueWrapper; finishSave: () => void }> {
	let finishSave!: () => void;
	useSchemaStore().saveSchema = () => new Promise<void>( ( resolve ) => {
		finishSave = resolve;
	} );
	const wrapper = mountDialog( [ item( 'Museum' ) ] );
	await runImport( wrapper );

	return { wrapper, finishSave };
}

describe( 'SchemaImportDialog', () => {
	beforeEach( () => {
		// Not ", ", so names joined with an English comma instead of the wiki's own separator show.
		setupMwMock( { messages: { 'comma-separator': '、' }, functions: [ 'msg' ] } );

		pinia = createPinia();
		setActivePinia( pinia );
		useSchemaStore().saveSchema = vi.fn().mockResolvedValue( undefined );
	} );

	it( 'checks the Schemas this wiki lacks, and leaves the ones it has unchecked', () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ) ] );

		expect( rows( wrapper ).map( ( row ) => [ row.name, row.checked ] ) ).toEqual( [
			[ 'Artist', true ],
			[ 'Person', false ],
			[ 'Museum', true ],
		] );
	} );

	it( 'marks each Schema that would replace the one this wiki has', () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ) ] );

		expect( rows( wrapper ).map( ( row ) => [ row.name, row.replaces ] ) ).toEqual( [
			[ 'Artist', false ],
			[ 'Person', true ],
			[ 'Museum', false ],
		] );
		expect( wrapper.find( '.cdx-label__description' ).text() ).toBe( 'neowiki-schemas-import-replaces' );
	} );

	it( 'counts the checked Schemas on the import action', async () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ) ] );

		expect( primaryAction( wrapper ).text() ).toBe( 'neowiki-schemas-import-confirm2' );

		await setChecked( wrapper, 'Person', true );

		expect( primaryAction( wrapper ).text() ).toBe( 'neowiki-schemas-import-confirm3' );
	} );

	it( 'disables the import action while no Schema is checked', async () => {
		const wrapper = mountDialog( [ item( 'Person', true ), item( 'Museum' ) ] );

		await setChecked( wrapper, 'Museum', false );

		expect( primaryAction( wrapper ).element.disabled ).toBe( true );

		await setChecked( wrapper, 'Person', true );

		expect( primaryAction( wrapper ).element.disabled ).toBe( false );
	} );

	it( 'saves the checked Schemas in file order, with the import edit summary', async () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ), item( 'Place', true ) ] );
		// Checked after Museum, but before it in the file.
		await setChecked( wrapper, 'Person', true );
		await setChecked( wrapper, 'Artist', false );

		await runImport( wrapper );

		expect( savedNames() ).toEqual( [ 'Person', 'Museum' ] );
		expect( useSchemaStore().saveSchema ).toHaveBeenCalledWith( expect.anything(), 'neowiki-schemas-import-summary' );
	} );

	it( 'shows which Schemas it created, which it replaced, and which failed and why', async () => {
		useSchemaStore().saveSchema = vi.fn( async ( schema: Schema ): Promise<void> => {
			if ( schema.getName() === 'Place' ) {
				throw new Error( 'The page is protected' );
			}
		} );
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', true ), item( 'Museum' ), item( 'Place' ) ] );
		await setChecked( wrapper, 'Person', true );

		await runImport( wrapper );

		expect( messages( wrapper, 'success' ) ).toEqual( [
			'neowiki-schemas-import-created2Artist、Museum',
			'neowiki-schemas-import-replaced1Person',
		] );
		expect( messages( wrapper, 'error' ) ).toEqual( [ 'neowiki-schemas-import-failedPlaceThe page is protected' ] );
	} );

	it( 'reports nothing as created or replaced when every save fails', async () => {
		useSchemaStore().saveSchema = vi.fn().mockRejectedValue( new Error( 'The wiki is read-only' ) );
		const wrapper = mountDialog( [ item( 'Person', true ), item( 'Museum' ) ] );
		await setChecked( wrapper, 'Person', true );

		await runImport( wrapper );

		expect( messages( wrapper, 'success' ) ).toEqual( [] );
		expect( messages( wrapper, 'error' ) ).toHaveLength( 2 );
	} );

	it( 'tells its host once the import has run, not before', async () => {
		const { wrapper, finishSave } = await importWithSaveHeld();

		expect( wrapper.emitted( 'imported' ) ).toBeUndefined();

		finishSave();
		await flushPromises();

		expect( wrapper.emitted( 'imported' ) ).toHaveLength( 1 );
	} );

	it( 'closes on cancel without saving', async () => {
		const wrapper = mountDialog( [ item( 'Museum' ) ] );

		await defaultAction( wrapper ).trigger( 'click' );

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
		expect( useSchemaStore().saveSchema ).not.toHaveBeenCalled();
	} );

	it( 'offers to close once the import has run', async () => {
		const wrapper = mountDialog( [ item( 'Museum' ) ] );
		await runImport( wrapper );

		expect( primaryAction( wrapper ).exists() ).toBe( false );
		expect( defaultAction( wrapper ).text() ).toBe( 'cdx-dialog-close-button-label' );
	} );

	it( 'lets the keyboard close it once the import has run', async () => {
		const wrapper = mountDialog( [ item( 'Museum' ) ] );
		await runImport( wrapper );

		document.activeElement!.dispatchEvent( new KeyboardEvent( 'keyup', { key: 'Escape', bubbles: true } ) );

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'stays open, its actions disabled, until the import has run', async () => {
		const { wrapper, finishSave } = await importWithSaveHeld();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'close' ) ).toBeUndefined();
		expect( wrapper.find( '.cdx-dialog__header__close-button' ).exists() ).toBe( false );
		expect( primaryAction( wrapper ).element.disabled ).toBe( true );
		expect( defaultAction( wrapper ).element.disabled ).toBe( true );
		expect( checkboxOf( wrapper, 'Museum' ).element.disabled ).toBe( true );

		finishSave();
		await flushPromises();
		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );
} );
