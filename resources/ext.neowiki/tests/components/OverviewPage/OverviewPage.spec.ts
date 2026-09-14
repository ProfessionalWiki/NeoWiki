import { mount, DOMWrapper, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { CdxButton, CdxCard } from '@wikimedia/codex';
import OverviewPage from '@/components/OverviewPage/OverviewPage.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

let grantedRight = true;
const canCreateSubjectPageRef = ref( false );
const checkCreateSubjectPagePermissionMock = vi.fn( async (): Promise<void> => {
	canCreateSubjectPageRef.value = grantedRight;
} );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage: canCreateSubjectPageRef,
		checkCreateSubjectPagePermission: checkCreateSubjectPagePermissionMock,
	} ),
} ) );

const SubjectCreatorDialogStub = {
	template: '<div class="subject-creator-stub" />',
	props: [ 'hostPage', 'initialSchemaName', 'open' ],
	emits: [ 'update:open' ],
};

function summary( name: string, description = '' ): SchemaSummary {
	return { name, description, propertyCount: 1 };
}

// The pages the map holds whatever the reader may do. The permission-dependent ones are appended.
const ALWAYS_MAPPED = [ '/wiki/Special:Layouts', '/wiki/Special:Mappings' ];

const PERSON = summary( 'Person', 'Someone' );
const PAINTING = summary( 'Painting', 'Something hung on a wall' );
const MUSEUM = summary( 'Museum', 'Somewhere paintings hang' );

let pinia: ReturnType<typeof createPinia>;
let schemaStore: ReturnType<typeof useSchemaStore>;

interface OverviewProps {
	canManageGraphStores: boolean;
	canEditConfiguration: boolean;
}

function mountPage( offered: Partial<OverviewProps> = {} ): VueWrapper {
	return mount( OverviewPage, {
		props: { canManageGraphStores: false, canEditConfiguration: false, ...offered },
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			stubs: { SubjectCreatorDialog: SubjectCreatorDialogStub, CdxIcon: true },
		},
	} );
}

function listSchemas( summaries: SchemaSummary[] ): void {
	schemaStore.fetchAllSchemaSummaries = vi.fn().mockResolvedValue( summaries );
}

function findCreateButton( wrapper: VueWrapper, schemaName: string ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxButton )
		.find( ( button ) => button.text().includes( `neowiki-schema-create-subject${ schemaName }` ) );
}

function findPickerButton( wrapper: VueWrapper ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxButton )
		.find( ( button ) => button.text().includes( 'neowiki-createsubject-button' ) );
}

function findLink( wrapper: VueWrapper, href: string ): DOMWrapper<HTMLAnchorElement> | undefined {
	return wrapper.findAll( 'a' ).find( ( link ) => link.attributes( 'href' ) === href );
}

/**
 * Where each card in the map goes, in the order the map shows them. A card given a url is itself
 * the link, so its own href is the destination.
 */
function mappedPages( wrapper: VueWrapper ): ( string | undefined )[] {
	return wrapper.findAllComponents( CdxCard ).map( ( card ) => card.attributes( 'href' ) );
}

describe( 'OverviewPage', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'util', 'notify' ] } );
		grantedRight = true;
		canCreateSubjectPageRef.value = false;
		pinia = createPinia();
		setActivePinia( pinia );
		schemaStore = useSchemaStore();
		listSchemas( [ PERSON, PAINTING, MUSEUM ] );
	} );

	it( 'lists every schema with its description and a link to its page', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( findLink( wrapper, '/wiki/Schema:Painting' )!.text() ).toBe( 'Painting' );
		expect( wrapper.text() ).toContain( 'Something hung on a wall' );
	} );

	it( 'opens the creator pinned to the schema whose button was clicked', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await findCreateButton( wrapper, 'Painting' )!.trigger( 'click' );
		await flushPromises();

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'Painting' );
		expect( dialog.props( 'hostPage' ) ).toBeNull();
		expect( useSubjectStore().subjectCreatorOpen ).toBe( true );
	} );

	it( 'opens the creator on its schema picker from the button above the list', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await findCreateButton( wrapper, 'Painting' )!.trigger( 'click' );
		await flushPromises();
		useSubjectStore().closeSubjectCreator();

		await findPickerButton( wrapper )!.trigger( 'click' );
		await flushPromises();

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'initialSchemaName' ) ).toBeUndefined();
		expect( useSubjectStore().subjectCreatorOpen ).toBe( true );
	} );

	it( 'shows the creator open once it is asked for', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await findCreateButton( wrapper, 'Painting' )!.trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'closes the creator in the store when the dialog closes', async () => {
		const wrapper = mountPage();
		await flushPromises();
		useSubjectStore().openSubjectCreator();

		wrapper.findComponent( SubjectCreatorDialog ).vm.$emit( 'update:open', false );

		expect( useSubjectStore().subjectCreatorOpen ).toBe( false );
	} );

	it( 'offers no way to create a subject to a user who may not', async () => {
		grantedRight = false;
		const wrapper = mountPage();
		await flushPromises();

		expect( findPickerButton( wrapper ) ).toBeUndefined();
		expect( findCreateButton( wrapper, 'Painting' ) ).toBeUndefined();
		expect( wrapper.findComponent( SubjectCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'says the wiki has no schemas instead of offering to create a subject', async () => {
		listSchemas( [] );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-schemas-empty' );
		expect( findPickerButton( wrapper ) ).toBeUndefined();
	} );

	it( 'maps the other NeoWiki pages, and nothing the reader may not open', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( mappedPages( wrapper ) ).toEqual( ALWAYS_MAPPED );
	} );

	it( 'maps the graph stores for a user who may manage them', async () => {
		const wrapper = mountPage( { canManageGraphStores: true } );
		await flushPromises();

		expect( mappedPages( wrapper ) ).toEqual( [ ...ALWAYS_MAPPED, '/wiki/Special:GraphStores' ] );
	} );

	it( 'maps the configuration page for a user who may edit it', async () => {
		const wrapper = mountPage( { canEditConfiguration: true } );
		await flushPromises();

		expect( mappedPages( wrapper ) ).toEqual( [ ...ALWAYS_MAPPED, '/wiki/MediaWiki:NeoWiki' ] );
	} );

	it( 'reaches Special:Schemas from the table header rather than from the map', async () => {
		const wrapper = mountPage();
		await flushPromises();

		const link = findLink( wrapper, '/wiki/Special:Schemas' )!;

		expect( link.text() ).toBe( 'neowiki-overview-manage-schemas' );
		expect( link.element.closest( '.cdx-table__header' ) ).not.toBeNull();
	} );

	it( 'shows the create button, then the map, then the schema table', async () => {
		const wrapper = mountPage();
		await flushPromises();

		const sections = wrapper.element.children;

		expect( sections[ 0 ].contains( findPickerButton( wrapper )!.element ) ).toBe( true );
		expect( sections[ 1 ].contains( wrapper.findComponent( CdxCard ).element ) ).toBe( true );
		expect( sections[ 2 ].contains( wrapper.find( 'table' ).element ) ).toBe( true );
	} );

	it( 'reports a listing that could not be read', async () => {
		schemaStore.fetchAllSchemaSummaries = vi.fn()
			.mockRejectedValue( new Error( 'Error fetching schema summaries' ) );

		mountPage();
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'Error fetching schema summaries', { type: 'error' } );
	} );
} );
