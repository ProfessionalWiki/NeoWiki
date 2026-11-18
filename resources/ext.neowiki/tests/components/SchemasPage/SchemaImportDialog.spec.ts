import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { CdxDialog } from '@wikimedia/codex';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import type { ModelBundle } from '@/application/modelBundle/ModelBundle.ts';
import type { Schema } from '@/domain/Schema.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import { checkboxLabelled, createI18nMock, pressHeaderClose, setupMwMock } from '../../VueTestHelpers.ts';
import { InMemoryDataModel } from '../../application/modelBundle/InMemoryDataModel.ts';
import { bundleOf, installed, subjectEntry, VOCABULARIES } from '../../application/modelBundle/builders.ts';

enableAutoUnmount( afterEach );

const SUBJECT_ID = 's1demo1aaaaaaa1';

let pinia: ReturnType<typeof createPinia>;
let wiki: InMemoryDataModel;

function file( schemas: Schema[], extra: Partial<ModelBundle> = {} ): ModelBundle {
	return bundleOf( [], schemas, { metadata: null, ...extra } );
}

async function mountDialog( bundle: ModelBundle ): Promise<VueWrapper> {
	const wrapper = mount( SchemaImportDialog, {
		props: { bundle },
		attachTo: document.body,
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.DataModelRepository ]: wiki,
				[ Service.InstallRecordRepository ]: wiki,
			},
			stubs: { teleport: true },
		},
	} );
	await flushPromises();

	return wrapper;
}

/**
 * Each row of the preview: what it names, whether it is checked (null for a row without a checkbox), and
 * what it says of the item.
 */
function rows( wrapper: VueWrapper ): [ string, boolean | null, string ][] {
	return wrapper.findAll( '.ext-neowiki-bundle-preview__item' ).map( ( row ) => {
		const checkbox = row.find<HTMLInputElement>( 'input[type="checkbox"]' );

		return checkbox.exists() ?
			[ row.find( '.cdx-label__label__text' ).text(), checkbox.element.checked, row.find( '.cdx-label__description' ).text() ] :
			[ row.find( '.ext-neowiki-bundle-preview__name' ).text(), null, row.find( '.ext-neowiki-bundle-preview__status' ).text() ];
	} );
}

function button( wrapper: VueWrapper, label: string ): DOMWrapper<HTMLButtonElement> {
	const found = wrapper.findAll<HTMLButtonElement>( 'button' ).find( ( candidate ) => candidate.text() === label );

	expect( found ).toBeDefined();
	return found!;
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

function resultHeadings( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-import-results__heading' ).map( ( heading ) => heading.text() );
}

async function importWithWriteHeld(): Promise<{ wrapper: VueWrapper; finishWrite: () => void }> {
	let finishWrite!: () => void;
	const save = wiki.saveSchema.bind( wiki );
	wiki.saveSchema = ( name, json, comment, baseRevisionId ) => new Promise<void>( ( resolve ) => {
		finishWrite = () => save( name, json, comment, baseRevisionId ).then( resolve );
	} );
	const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ] ) );
	await primaryAction( wrapper ).trigger( 'click' );
	await flushPromises();

	return { wrapper, finishWrite };
}

describe( 'SchemaImportDialog', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'config' ] } );
		pinia = createPinia();
		setActivePinia( pinia );
		wiki = new InMemoryDataModel().withSchemas( newSchema( { title: 'Person', description: 'Old' } ) );
	} );

	it( 'checks what the wiki lacks and leaves unchecked what it would replace, saying so', async () => {
		const wrapper = await mountDialog( file( [
			newSchema( { title: 'Person', description: 'New' } ),
			newSchema( { title: 'Museum' } ),
		] ) );

		expect( rows( wrapper ) ).toEqual( [
			[ 'Museum', true, 'neowiki-bundle-status-new' ],
			[ 'Person', false, 'neowiki-bundle-status-differs' ],
		] );
	} );

	it( 'says, in a plain import, which installed bundle an item belongs to', async () => {
		wiki.records = [ installed( [], [ newSchema( { title: 'Person', description: 'Old' } ) ] ) ];

		const wrapper = await mountDialog( file( [ newSchema( { title: 'Person', description: 'New' } ) ] ) );

		expect( wrapper.find( '.ext-neowiki-bundle-preview__item .ext-neowiki-bundle-preview__note' ).text() )
			.toBe( 'neowiki-bundle-belongs-toVocabularies' );
	} );

	it( 'says that installing a bundle replaces what differs', async () => {
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Person', description: 'New' } ) ], { metadata: VOCABULARIES } ) );

		expect( rows( wrapper ) ).toEqual( [ [ 'Person', false, 'neowiki-bundle-status-differs-install' ] ] );
	} );

	it( 'lists the Subjects under their Schema, with what it cannot import and why', async () => {
		wiki.withSubject( SUBJECT_ID, subjectEntry( { schema: 'Person' } ) );

		const wrapper = await mountDialog( file( [], { subjects: new Map( [ [ SUBJECT_ID, subjectEntry( { label: 'female' } ) ] ] ) } ) );

		expect( wrapper.find( '.ext-neowiki-bundle-preview__group-heading' ).text() ).toBe( 'neowiki-bundle-preview-schema-groupGender Types1' );
		expect( rows( wrapper ) ).toEqual( [
			[ 'female', null, 'neowiki-bundle-status-conflictneowiki-bundle-conflict-subject-schemaPerson' ],
		] );
	} );

	it( 'says that a bundle install cannot install what is in conflict', async () => {
		wiki.withSubject( SUBJECT_ID, subjectEntry( { schema: 'Person' } ) );

		const wrapper = await mountDialog( file( [], {
			metadata: VOCABULARIES,
			subjects: new Map( [ [ SUBJECT_ID, subjectEntry( { label: 'female' } ) ] ] ),
		} ) );

		expect( rows( wrapper ) ).toEqual( [
			[ 'female', null, 'neowiki-bundle-status-conflict-installneowiki-bundle-conflict-subject-schemaPerson' ],
		] );
	} );

	it( 'folds away only what is unchanged, keeping in view what this wiki changed and what cannot be imported', async () => {
		wiki.records = [ installed( [], [ newSchema( { title: 'Person', description: 'Recorded' } ) ] ) ];
		wiki.withSchemas( newSchema( { title: 'Museum' } ) );
		wiki.unreadable.add( 'schema:Place' );
		wiki.withSchemas( newSchema( { title: 'Place' } ) );

		const wrapper = await mountDialog( file( [
			newSchema( { title: 'Person', description: 'Recorded' } ),
			newSchema( { title: 'Museum' } ),
			newSchema( { title: 'Place' } ),
		], { metadata: VOCABULARIES } ) );

		expect( wrapper.findAll( '.ext-neowiki-bundle-preview__quiet .ext-neowiki-bundle-preview__name' ).map( ( name ) => name.text() ) )
			.toEqual( [ 'Museum' ] );
	} );

	it( 'shows the changes of an item that differs from the wiki\'s', async () => {
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Person', description: 'New' } ) ] ) );

		await button( wrapper, 'neowiki-bundle-preview-show-changes' ).trigger( 'click' );

		expect( wrapper.find( '.ext-neowiki-version-diff__lines .ext-neowiki-version-diff__line--added' ).text() ).toContain( '"description": "New"' );
	} );

	it( 'offers no changes to show for a Subject in conflict', async () => {
		wiki.withSubject( SUBJECT_ID, subjectEntry( { schema: 'Person' } ) );

		const wrapper = await mountDialog( file( [], { subjects: new Map( [ [ SUBJECT_ID, subjectEntry( { label: 'female' } ) ] ] ) } ) );

		expect( wrapper.findAll( 'button' ).map( ( button ) => button.text() ) ).not.toContain( 'neowiki-bundle-preview-show-changes' );
	} );

	it( 'says in a line of its own why it keeps what the bundle no longer has', async () => {
		wiki.records = [ installed( [ [ SUBJECT_ID, subjectEntry() ] ] ) ];
		wiki.withSubject( SUBJECT_ID, subjectEntry() );
		wiki.referencingSubjects.set( SUBJECT_ID, [ 's1demo1aaaaaaa9' ] );

		const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ], { metadata: VOCABULARIES } ) );

		expect( rows( wrapper ) ).toContainEqual( [ 'female', null, 'neowiki-bundle-remove-blocked-referenced' ] );
	} );

	it( 'lists a Subject it could not read by its recorded label, under its recorded Schema', async () => {
		wiki.records = [ installed( [ [ SUBJECT_ID, subjectEntry() ] ] ) ];
		wiki.withSubject( SUBJECT_ID, subjectEntry() );
		wiki.unreadable.add( `subject:${ SUBJECT_ID }` );

		const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ], { metadata: VOCABULARIES } ) );

		expect( wrapper.findAll( '.ext-neowiki-bundle-preview__group-heading' ).map( ( heading ) => heading.text() ) )
			.toEqual( [ 'neowiki-bundle-preview-all1', 'neowiki-bundle-preview-schema-groupGender Types1' ] );
		expect( rows( wrapper ).map( ( [ name ] ) => name ) ).toContain( 'female' );
	} );

	it( 'tells of a taken page title for a Subject it would create again, apart from its status', async () => {
		wiki.records = [ installed( [ [ SUBJECT_ID, subjectEntry( { label: 'female' } ) ] ] ) ];
		wiki.takenPageTitles.add( 'woman' );

		const wrapper = await mountDialog( file( [], {
			metadata: VOCABULARIES,
			subjects: new Map( [ [ SUBJECT_ID, subjectEntry( { label: 'woman' } ) ] ] ),
		} ) );

		const description = wrapper.find( '.ext-neowiki-bundle-preview__item .cdx-label__description' );
		expect( description.text() ).toContain( 'neowiki-bundle-status-deleted-here-changed-upstream' );
		expect( description.find( '.ext-neowiki-bundle-preview__note' ).text() ).toBe( `neowiki-bundle-page-title-taken${ SUBJECT_ID }` );
	} );

	it( 'checks or unchecks every item of a group from its heading', async () => {
		const wrapper = await mountDialog( file( [
			newSchema( { title: 'Person', description: 'New' } ),
			newSchema( { title: 'Museum' } ),
		] ) );

		await wrapper.find<HTMLInputElement>( '.ext-neowiki-bundle-preview__group-heading input' ).setValue( true );

		expect( rows( wrapper ).map( ( [ , checked ] ) => checked ) ).toEqual( [ true, true ] );
	} );

	it( 'leaves removals unchecked when checking a whole group from its heading', async () => {
		wiki.records = [ installed( [], [ newSchema( { title: 'Person' } ) ] ) ];
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ], { metadata: VOCABULARIES } ) );
		await checkboxLabelled( wrapper, 'Museum' ).setValue( false );

		await wrapper.find<HTMLInputElement>( '.ext-neowiki-bundle-preview__group-heading input' ).setValue( true );

		expect( [ checkboxLabelled( wrapper, 'Museum' ).element.checked, checkboxLabelled( wrapper, 'Person' ).element.checked ] ).toEqual( [ true, false ] );
	} );

	it( 'disables the import action while nothing is checked', async () => {
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ] ) );

		await checkboxLabelled( wrapper, 'Museum' ).setValue( false );

		expect( primaryAction( wrapper ).element.disabled ).toBe( true );
	} );

	it( 'writes the checked items and shows what it created, replaced and failed, and why', async () => {
		wiki.failingWrites.set( 'schema:Place', 'The page is protected' );
		const wrapper = await mountDialog( file( [
			newSchema( { title: 'Person', description: 'New' } ),
			newSchema( { title: 'Museum' } ),
			newSchema( { title: 'Place' } ),
		] ) );
		await checkboxLabelled( wrapper, 'Person' ).setValue( true );

		await runImport( wrapper );

		expect( wiki.writes ).toEqual( [ 'save schema:Museum', 'save schema:Person' ] );
		expect( resultHeadings( wrapper ) ).toEqual( [
			'neowiki-bundle-result-failed1',
			'neowiki-bundle-result-created1',
			'neowiki-bundle-result-replaced1',
		] );
		expect( wrapper.find( '.ext-neowiki-import-results .cdx-message--error' ).text() ).toBe( 'The page is protected' );
	} );

	it( 'installs a model bundle, recording it even when nothing is to be written', async () => {
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Person', description: 'Old' } ) ], {
			metadata: { name: 'Vocabularies', version: '1', description: '' },
		} ) );

		expect( primaryAction( wrapper ).text() ).toBe( 'neowiki-bundle-install-button' );

		await runImport( wrapper );

		expect( wiki.recordOf( 'Vocabularies' )?.items.map( ( item ) => item.key ) ).toEqual( [ 'Person' ] );
	} );

	it( 'has the Schema store read anew the Schemas it replaced', async () => {
		useSchemaStore().setSchema( 'Person', newSchema( { title: 'Person', description: 'Old' } ) );
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Person', description: 'New' } ) ] ) );
		await checkboxLabelled( wrapper, 'Person' ).setValue( true );

		await runImport( wrapper );

		expect( useSchemaStore().getSchemas.has( 'Person' ) ).toBe( false );
	} );

	it( 'tells its host once the import has run, not before', async () => {
		const { wrapper, finishWrite } = await importWithWriteHeld();

		expect( wrapper.emitted( 'imported' ) ).toBeUndefined();

		finishWrite();
		await flushPromises();

		expect( wrapper.emitted( 'imported' ) ).toHaveLength( 1 );
	} );

	// Shown on a phone, as Codex 2 shows it there too.
	it( 'ignores its header\'s Close while the import runs', async () => {
		const { wrapper } = await importWithWriteHeld();

		await pressHeaderClose( wrapper );

		expect( wrapper.emitted( 'close' ) ).toBeUndefined();
	} );

	it( 'stays open, its actions disabled, until the import has run', async () => {
		const { wrapper, finishWrite } = await importWithWriteHeld();

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'close' ) ).toBeUndefined();
		expect( primaryAction( wrapper ).element.disabled ).toBe( true );
		expect( defaultAction( wrapper ).element.disabled ).toBe( true );

		finishWrite();
		await flushPromises();
		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'closes on cancel without writing', async () => {
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ] ) );

		await defaultAction( wrapper ).trigger( 'click' );

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
		expect( wiki.writes ).toEqual( [] );
	} );

	it( 'lets the keyboard close it once the import has run', async () => {
		const wrapper = await mountDialog( file( [ newSchema( { title: 'Museum' } ) ] ) );
		await runImport( wrapper );

		expect( defaultAction( wrapper ).text() ).toBe( 'cdx-dialog-close-button-label' );

		document.activeElement!.dispatchEvent( new KeyboardEvent( 'keyup', { key: 'Escape', bubbles: true } ) );

		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );
} );
