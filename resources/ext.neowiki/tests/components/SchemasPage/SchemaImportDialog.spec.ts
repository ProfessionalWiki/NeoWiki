import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxDialog } from '@wikimedia/codex';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import type { SchemaImportItem, SchemaImportStatus } from '@/components/SchemasPage/schemaImport.ts';
import type { Schema } from '@/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

let pinia: ReturnType<typeof createPinia>;

function item( name: string, status: SchemaImportStatus = 'new' ): SchemaImportItem {
	return { schema: newSchema( { title: name } ), status };
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

function headings( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-schema-import-dialog__section' ).map( headingOf );
}

function headingOf( section: DOMWrapper<Element> ): string {
	const heading = section.find( '.ext-neowiki-schema-import-dialog__heading' );
	const label = heading.find( '.cdx-label__label__text' );

	return ( label.exists() ? label : heading ).text();
}

function section( wrapper: VueWrapper, heading: string ): DOMWrapper<Element> {
	const found = wrapper.findAll( '.ext-neowiki-schema-import-dialog__section' )
		.find( ( candidate ) => headingOf( candidate ) === heading );

	expect( found ).toBeDefined();
	return found!;
}

function rowsOf( wrapper: VueWrapper, heading: string ): [ string, boolean ][] {
	return section( wrapper, heading ).findAll( '.ext-neowiki-schema-import-dialog__items .cdx-checkbox' ).map( ( row ) => [
		row.find( '.cdx-label__label__text' ).text(),
		( row.find( 'input' ).element as HTMLInputElement ).checked,
	] );
}

function headingCheckbox( wrapper: VueWrapper, heading: string ): DOMWrapper<HTMLInputElement> {
	return section( wrapper, heading ).find<HTMLInputElement>( '.ext-neowiki-schema-import-dialog__heading input' );
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

	it( 'lists new Schemas checked and changed ones unchecked, each under its own heading', () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', 'changed' ), item( 'Museum' ) ] );

		expect( headings( wrapper ) ).toEqual( [ 'neowiki-schemas-import-new2', 'neowiki-schemas-import-changed1' ] );
		expect( rowsOf( wrapper, 'neowiki-schemas-import-new2' ) ).toEqual( [ [ 'Artist', true ], [ 'Museum', true ] ] );
		expect( rowsOf( wrapper, 'neowiki-schemas-import-changed1' ) ).toEqual( [ [ 'Person', false ] ] );
	} );

	it( 'lists the Schemas of each section alphabetically', () => {
		const wrapper = mountDialog( [
			item( 'museum' ), item( 'Zoo', 'changed' ), item( 'Artist' ), item( 'Place', 'unchanged' ),
			item( 'Person', 'changed' ), item( 'City' ), item( 'Office', 'unchanged' ),
		] );

		expect( rowsOf( wrapper, 'neowiki-schemas-import-new3' ).map( ( [ name ] ) => name ) ).toEqual( [ 'Artist', 'City', 'museum' ] );
		expect( rowsOf( wrapper, 'neowiki-schemas-import-changed2' ).map( ( [ name ] ) => name ) ).toEqual( [ 'Person', 'Zoo' ] );
		expect( section( wrapper, 'neowiki-schemas-import-unchanged2' ).text() )
			.toContain( 'neowiki-schemas-import-unchanged-namesOffice、Place' );
	} );

	it( 'says once, under its heading, that the changed Schemas replace the existing ones', () => {
		const wrapper = mountDialog( [ item( 'Person', 'changed' ), item( 'Place', 'changed' ) ] );

		expect( wrapper.findAll( '.cdx-label__description' ).map( ( description ) => description.text() ) )
			.toEqual( [ 'neowiki-schemas-import-replaces2' ] );
	} );

	it( 'names the unchanged Schemas without offering to import them', () => {
		const wrapper = mountDialog( [ item( 'Person', 'unchanged' ), item( 'Artist' ), item( 'Place', 'unchanged' ) ] );

		expect( section( wrapper, 'neowiki-schemas-import-unchanged2' ).text() )
			.toContain( 'neowiki-schemas-import-unchanged-namesPerson、Place' );
		expect( section( wrapper, 'neowiki-schemas-import-unchanged2' ).find( 'input' ).exists() ).toBe( false );
	} );

	it( 'checks or unchecks every Schema of a section from its heading', async () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', 'changed' ), item( 'Place', 'changed' ) ] );

		await headingCheckbox( wrapper, 'neowiki-schemas-import-changed2' ).setValue( true );

		expect( rowsOf( wrapper, 'neowiki-schemas-import-changed2' ) ).toEqual( [ [ 'Person', true ], [ 'Place', true ] ] );

		await headingCheckbox( wrapper, 'neowiki-schemas-import-new1' ).setValue( false );

		expect( rowsOf( wrapper, 'neowiki-schemas-import-new1' ) ).toEqual( [ [ 'Artist', false ] ] );
		expect( rowsOf( wrapper, 'neowiki-schemas-import-changed2' ) ).toEqual( [ [ 'Person', true ], [ 'Place', true ] ] );
	} );

	it( 'shows a section with only some Schemas checked as partly checked', async () => {
		const wrapper = mountDialog( [ item( 'Person', 'changed' ), item( 'Place', 'changed' ) ] );

		await setChecked( wrapper, 'Place', true );

		expect( headingCheckbox( wrapper, 'neowiki-schemas-import-changed2' ).element.indeterminate ).toBe( true );
		expect( headingCheckbox( wrapper, 'neowiki-schemas-import-changed2' ).element.checked ).toBe( false );
	} );

	it( 'counts the checked Schemas on the import action', async () => {
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', 'changed' ), item( 'Museum' ) ] );

		expect( primaryAction( wrapper ).text() ).toBe( 'neowiki-schemas-import-confirm2' );

		await setChecked( wrapper, 'Person', true );

		expect( primaryAction( wrapper ).text() ).toBe( 'neowiki-schemas-import-confirm3' );
	} );

	it( 'disables the import action while no Schema is checked', async () => {
		const wrapper = mountDialog( [ item( 'Person', 'changed' ), item( 'Museum' ) ] );

		await setChecked( wrapper, 'Museum', false );

		expect( primaryAction( wrapper ).element.disabled ).toBe( true );

		await setChecked( wrapper, 'Person', true );

		expect( primaryAction( wrapper ).element.disabled ).toBe( false );
	} );

	it( 'saves the checked Schemas in the order listed, with the import edit summary', async () => {
		const wrapper = mountDialog( [ item( 'Person', 'changed' ), item( 'Museum' ), item( 'Artist' ), item( 'Place', 'changed' ) ] );
		await setChecked( wrapper, 'Person', true );
		await setChecked( wrapper, 'Artist', false );

		await runImport( wrapper );

		expect( savedNames() ).toEqual( [ 'Museum', 'Person' ] );
		expect( useSchemaStore().saveSchema ).toHaveBeenCalledWith( expect.anything(), 'neowiki-schemas-import-summary' );
	} );

	it( 'shows which Schemas it created, which it replaced, and which failed and why', async () => {
		useSchemaStore().saveSchema = vi.fn( async ( schema: Schema ): Promise<void> => {
			if ( schema.getName() === 'Place' ) {
				throw new Error( 'The page is protected' );
			}
		} );
		const wrapper = mountDialog( [ item( 'Artist' ), item( 'Person', 'changed' ), item( 'Museum' ), item( 'Place' ) ] );
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
		const wrapper = mountDialog( [ item( 'Person', 'changed' ), item( 'Museum' ) ] );
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
