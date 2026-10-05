import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SchemaCard from '@/components/SchemasPage/SchemaCard.vue';
import SchemaCreatorDialog from '@/components/SchemasPage/SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import type { SchemaSummaryPage } from '@/application/SchemaLookup.ts';

// Each right reaches its ref only through its check, so a page that skips a check offers nothing
// however the fixture is set.
let mayCreateSchemas = false;
let mayEditSchemas = false;
let mayDeleteSchemas = false;
const canCreateSchemasRef = ref( false );
const canEditSchemaRef = ref( false );
const canDeleteSchemaRef = ref( false );

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canCreateSchemas: canCreateSchemasRef,
		canEditSchema: canEditSchemaRef,
		canDeleteSchema: canDeleteSchemaRef,
		checkCreatePermission: vi.fn( async (): Promise<void> => {
			canCreateSchemasRef.value = mayCreateSchemas;
		} ),
		checkEditPermission: vi.fn( async (): Promise<void> => {
			canEditSchemaRef.value = mayEditSchemas;
		} ),
		checkDeletePermission: vi.fn( async (): Promise<void> => {
			canDeleteSchemaRef.value = mayDeleteSchemas;
		} ),
	} ),
} ) );

let mayCreateSubjectPages = false;
const canCreateSubjectPageRef = ref( false );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage: canCreateSubjectPageRef,
		checkCreateSubjectPagePermission: vi.fn( async (): Promise<void> => {
			canCreateSubjectPageRef.value = mayCreateSubjectPages;
		} ),
	} ),
} ) );

const getSchemaMock = vi.fn();
const saveSchemaMock = vi.fn();
let getSchemaSummaries: ReturnType<typeof vi.fn>;
let pinia: ReturnType<typeof createPinia>;

// The store saves through the extension's repository.
vi.mock( '@/NeoWikiExtension.ts', () => ( {
	NeoWikiExtension: {
		getInstance: () => ( {
			getSchemaRepository: () => ( { saveSchema: saveSchemaMock } ),
		} ),
	},
} ) );

// Each card asks for its Subjects when it mounts, so the names mounted say which cards asked.
let mountedCardNames: string[] = [];

const SchemaCardStub = {
	name: 'SchemaCard',
	template: '<div class="schema-card-stub"></div>',
	props: [ 'summary', 'canEdit', 'canDelete', 'canCreateSubject', 'subjectListAvailable' ],
	emits: [ 'edit', 'delete', 'create-subject' ],
	mounted( this: { summary: { name: string } } ): void {
		mountedCardNames.push( this.summary.name );
	},
};

const SchemaCreatorDialogStub = {
	template: '<div></div>',
	props: [ 'open' ],
	emits: [ 'update:open', 'created' ],
};

const SubjectCreatorDialogStub = {
	template: '<div></div>',
	props: [ 'open', 'hostPage', 'initialSchemaName' ],
	emits: [ 'update:open' ],
};

const SchemaEditorDialogStub = {
	template: '<div></div>',
	props: [ 'open', 'initialSchema', 'onSave' ],
	emits: [ 'update:open', 'saved' ],
};

function page( names: string[], nextCursor: string | null = null ): SchemaSummaryPage {
	return { schemas: names.map( ( name ) => ( { name, description: '', propertyCount: 1 } ) ), nextCursor };
}

function mountPage( subjectListAvailable = true ): VueWrapper {
	setupMwMock( {
		functions: [ 'config', 'msg', 'util', 'message', 'notify' ],
		config: { wgNeoWikiSubjectListAvailable: subjectListAvailable },
	} );

	return mount( SchemasPage, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock, getSchemaSummaries },
			},
			stubs: {
				SchemaCard: SchemaCardStub,
				SchemaCreatorDialog: SchemaCreatorDialogStub,
				SchemaEditorDialog: SchemaEditorDialogStub,
				SubjectCreatorDialog: SubjectCreatorDialogStub,
				DeletePageDialog: true,
				CdxIcon: true,
			},
		},
	} );
}

function cards( wrapper: VueWrapper ): VueWrapper<InstanceType<typeof SchemaCard>>[] {
	return wrapper.findAllComponents( SchemaCard );
}

function cardNames( wrapper: VueWrapper ): string[] {
	return cards( wrapper ).map( ( card ) => card.props( 'summary' ).name );
}

function findShowMore( wrapper: VueWrapper ): ReturnType<VueWrapper['find']> {
	return wrapper.find( '.ext-neowiki-schemas-page__more' );
}

async function askToDelete( wrapper: VueWrapper, cardIndex: number ): Promise<void> {
	cards( wrapper )[ cardIndex ].vm.$emit( 'delete' );
	await flushPromises();
}

async function confirmDeleted( wrapper: VueWrapper, pageTitle: string ): Promise<void> {
	wrapper.findComponent( DeletePageDialog ).vm.$emit( 'deleted', pageTitle );
	await flushPromises();
}

async function searchFor( wrapper: VueWrapper, text: string ): Promise<void> {
	await wrapper.find( 'input[type="search"]' ).setValue( text );
	vi.advanceTimersByTime( 300 );
	await flushPromises();
}

describe( 'SchemasPage', () => {

	beforeEach( () => {
		vi.useFakeTimers();
		mayCreateSchemas = false;
		mayEditSchemas = false;
		mayDeleteSchemas = false;
		canCreateSchemasRef.value = false;
		canEditSchemaRef.value = false;
		canDeleteSchemaRef.value = false;
		mayCreateSubjectPages = false;
		canCreateSubjectPageRef.value = false;
		getSchemaMock.mockReset();
		saveSchemaMock.mockReset().mockResolvedValue( undefined );
		getSchemaSummaries = vi.fn().mockResolvedValue( page( [ 'Artist', 'Artwork', 'City' ] ) );
		pinia = createPinia();
		setActivePinia( pinia );
		mountedCardNames = [];
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'asks for the first twelve Schemas', async () => {
		mountPage();
		await flushPromises();

		expect( getSchemaSummaries ).toHaveBeenCalledWith( '', null, 12 );
	} );

	it( 'shows a card for each Schema', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Artwork', 'City' ] );
	} );

	it( 'tells the cards whether the wiki can list Subjects', async () => {
		const wrapper = mountPage( false );
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'subjectListAvailable' ) ).toBe( false );
	} );

	it( 'adds the next Schemas below the ones shown', async () => {
		getSchemaSummaries
			.mockResolvedValueOnce( page( [ 'Artist', 'Artwork' ], 'after-artwork' ) )
			.mockResolvedValueOnce( page( [ 'City' ] ) );
		const wrapper = mountPage();
		await flushPromises();

		await findShowMore( wrapper ).trigger( 'click' );
		await flushPromises();

		expect( getSchemaSummaries ).toHaveBeenLastCalledWith( '', 'after-artwork', 12 );
		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Artwork', 'City' ] );
	} );

	it( 'offers no more Schemas once the listing ends', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( findShowMore( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows the Schemas that contain the search once typing stops', async () => {
		const wrapper = mountPage();
		await flushPromises();
		getSchemaSummaries.mockResolvedValue( page( [ 'Artist', 'Artwork' ] ) );

		await wrapper.find( 'input[type="search"]' ).setValue( 'ar' );
		vi.advanceTimersByTime( 299 );
		await flushPromises();
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );

		await searchFor( wrapper, ' art ' );

		expect( getSchemaSummaries ).toHaveBeenLastCalledWith( 'art', null, 12 );
		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Artwork' ] );
	} );

	it( 'keeps the cards a search still shows instead of mounting them again', async () => {
		const wrapper = mountPage();
		await flushPromises();
		getSchemaSummaries.mockResolvedValue( page( [ 'Artist', 'Artwork' ] ) );
		mountedCardNames = [];

		await searchFor( wrapper, 'art' );

		expect( mountedCardNames ).toEqual( [] );
	} );

	it( 'says nothing about an empty listing when the search failed', async () => {
		const wrapper = mountPage();
		await flushPromises();
		getSchemaSummaries.mockRejectedValue( new Error( 'Error fetching schema summaries' ) );

		await searchFor( wrapper, 'zz' );

		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-no-match' );
		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-empty' );
	} );

	it( 'says nothing about an empty listing while more Schemas can be shown', async () => {
		mayDeleteSchemas = true;
		getSchemaSummaries.mockResolvedValue( page( [ 'Artist' ], 'more' ) );
		const wrapper = mountPage();
		await flushPromises();
		await askToDelete( wrapper, 0 );

		await confirmDeleted( wrapper, 'Schema:Artist' );

		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-empty' );
		expect( findShowMore( wrapper ).exists() ).toBe( true );
	} );

	it( 'says nothing about an empty listing while the Schemas load', async () => {
		getSchemaSummaries.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-empty' );
	} );

	it( 'asks once when typing pauses for less than the delay', async () => {
		const wrapper = mountPage();
		await flushPromises();
		getSchemaSummaries.mockClear();

		await wrapper.find( 'input[type="search"]' ).setValue( 'ar' );
		vi.advanceTimersByTime( 200 );
		await wrapper.find( 'input[type="search"]' ).setValue( 'art' );
		vi.advanceTimersByTime( 300 );
		await flushPromises();

		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'drops more Schemas that arrive after a newer search', async () => {
		let answerShowMore: ( value: SchemaSummaryPage ) => void = () => undefined;
		getSchemaSummaries
			.mockResolvedValueOnce( page( [ 'Artist', 'Artwork' ], 'after-artwork' ) )
			.mockReturnValueOnce( new Promise( ( resolve ) => {
				answerShowMore = resolve;
			} ) )
			.mockResolvedValueOnce( page( [ 'City' ] ) );
		const wrapper = mountPage();
		await flushPromises();
		await findShowMore( wrapper ).trigger( 'click' );

		await searchFor( wrapper, 'ci' );
		answerShowMore( page( [ 'Bridge' ] ) );
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( [ 'City' ] );
	} );

	it( 'shows the cards of the newest search when an older one answers last', async () => {
		let answerFirst: ( value: SchemaSummaryPage ) => void = () => undefined;
		getSchemaSummaries
			.mockReturnValueOnce( new Promise( ( resolve ) => {
				answerFirst = resolve;
			} ) )
			.mockResolvedValueOnce( page( [ 'City' ] ) );
		const wrapper = mountPage();

		await searchFor( wrapper, 'ci' );
		answerFirst( page( [ 'Artist' ] ) );
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( [ 'City' ] );
	} );

	it( 'says when no Schema matches the search', async () => {
		const wrapper = mountPage();
		await flushPromises();
		getSchemaSummaries.mockResolvedValue( page( [] ) );

		await searchFor( wrapper, 'zz' );

		expect( wrapper.text() ).toContain( 'neowiki-schemas-no-matchzz' );
	} );

	it( 'says when the wiki has no Schemas', async () => {
		getSchemaSummaries.mockResolvedValue( page( [] ) );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-schemas-empty' );
	} );

	it( 'reports Schemas that could not be loaded', async () => {
		getSchemaSummaries.mockRejectedValue( new Error( 'Error fetching schema summaries' ) );
		const wrapper = mountPage();
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'Error fetching schema summaries', { type: 'error' } );
		expect( wrapper.text() ).not.toContain( 'neowiki-schemas-empty' );
	} );

	it( 'offers Schema creation to a user who may create Schemas', async () => {
		mayCreateSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		await wrapper.find( '.ext-neowiki-schemas-page__create' ).trigger( 'click' );

		expect( wrapper.findComponent( SchemaCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'offers no Schema creation to a user who may not create Schemas', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.find( '.ext-neowiki-schemas-page__create' ).exists() ).toBe( false );
		expect( wrapper.findComponent( SchemaCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'lists the Schemas again after one is created', async () => {
		mayCreateSchemas = true;
		const wrapper = mountPage();
		await flushPromises();
		getSchemaSummaries.mockResolvedValue( page( [ 'Artist', 'Artwork', 'Bridge', 'City' ] ) );

		wrapper.findComponent( SchemaCreatorDialog ).vm.$emit( 'created' );
		await flushPromises();

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'Artwork', 'Bridge', 'City' ] );
	} );

	it( 'lets the cards offer editing to a user who may edit Schemas', async () => {
		mayEditSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canEdit' ) ).toBe( true );
	} );

	it( 'lets the cards offer deleting to a user who may delete Schemas', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canDelete' ) ).toBe( true );
	} );

	it( 'opens the editor on the Schema of the card', async () => {
		mayEditSchemas = true;
		const artwork = newSchema( { title: 'Artwork' } );
		getSchemaMock.mockResolvedValue( artwork );
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 1 ].vm.$emit( 'edit' );
		await flushPromises();

		expect( getSchemaMock ).toHaveBeenCalledWith( 'Artwork' );
		const editor = wrapper.findComponent( SchemaEditorDialog );
		expect( editor.props( 'open' ) ).toBe( true );
		expect( editor.props( 'initialSchema' ) ).toStrictEqual( artwork );
	} );

	it( 'reports a Schema that could not be fetched instead of opening the editor', async () => {
		mayEditSchemas = true;
		getSchemaMock.mockRejectedValue( new Error( 'Error fetching schema' ) );
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 0 ].vm.$emit( 'edit' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'Error fetching schema', { type: 'error' } );
		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
	} );

	it( 'shows the saved description on the card of the edited Schema', async () => {
		mayEditSchemas = true;
		getSchemaMock.mockResolvedValue( newSchema( { title: 'Artwork' } ) );
		const wrapper = mountPage();
		await flushPromises();
		cards( wrapper )[ 1 ].vm.$emit( 'edit' );
		await flushPromises();

		await wrapper.findComponent( SchemaEditorDialog ).props( 'onSave' )(
			new Schema( 'Artwork', 'A work of art.', new PropertyDefinitionList( [] ) ),
			'comment',
		);
		await flushPromises();

		expect( cards( wrapper )[ 1 ].props( 'summary' ).description ).toBe( 'A work of art.' );
	} );

	it( 'asks to confirm deleting the Schema of the card', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();

		await askToDelete( wrapper, 1 );

		const dialog = wrapper.findComponent( DeletePageDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'pageTitle' ) ).toBe( 'Schema:Artwork' );
	} );

	it( 'removes the card and the stored Schema once deleted', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();
		const store = useSchemaStore();
		store.setSchema( 'Artwork', newSchema( { title: 'Artwork' } ) );
		await askToDelete( wrapper, 1 );

		await confirmDeleted( wrapper, 'Schema:Artwork' );

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'City' ] );
		expect( () => store.getSchema( 'Artwork' ) ).toThrow();
	} );

	it( 'removes the card of the Schema deleted, not of the one asked about meanwhile', async () => {
		mayDeleteSchemas = true;
		const wrapper = mountPage();
		await flushPromises();
		await askToDelete( wrapper, 1 );
		await askToDelete( wrapper, 2 );

		await confirmDeleted( wrapper, 'Schema:Artwork' );

		expect( cardNames( wrapper ) ).toEqual( [ 'Artist', 'City' ] );
	} );

	it( 'keeps the Schema editor from a user who may not edit Schemas', async () => {
		getSchemaMock.mockResolvedValue( newSchema( { title: 'Artwork' } ) );
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 1 ].vm.$emit( 'edit' );
		await flushPromises();

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
	} );

	it( 'lets the cards offer Subject creation to a user who may create Subject pages', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canCreateSubject' ) ).toBe( true );
	} );

	it( 'opens the Subject creator on the Schema of the card', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();

		cards( wrapper )[ 2 ].vm.$emit( 'create-subject' );
		await flushPromises();

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'City' );
	} );

	it( 'closes the Subject creator when it asks to close', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();
		cards( wrapper )[ 0 ].vm.$emit( 'create-subject' );
		await flushPromises();

		wrapper.findComponent( SubjectCreatorDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'keeps the Subject creator from a user who may not create Subject pages', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( cards( wrapper )[ 0 ].props( 'canCreateSubject' ) ).toBe( false );
		expect( wrapper.findComponent( SubjectCreatorDialog ).exists() ).toBe( false );
	} );

} );
