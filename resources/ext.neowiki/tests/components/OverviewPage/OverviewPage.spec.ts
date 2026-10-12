import { enableAutoUnmount, mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { CdxButton, CdxCard } from '@wikimedia/codex';
import OverviewPage from '@/components/OverviewPage/OverviewPage.vue';
import RecentSubjectsTable from '@/components/SubjectsTable/RecentSubjectsTable.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
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

// A page left mounted would re-render under the next test's mw, which may lack what the page reads.
enableAutoUnmount( afterEach );

const SubjectCreatorDialogStub = {
	template: '<div class="subject-creator-stub" />',
	props: [ 'hostPage', 'open' ],
	emits: [ 'update:open' ],
};

// The pages the map holds whatever the reader may do. The permission-dependent ones are appended.
const ALWAYS_MAPPED = [ '/wiki/Special:Subjects', '/wiki/Special:Schemas', '/wiki/Special:Mappings', '/wiki/Special:Layouts' ];

let pinia: ReturnType<typeof createPinia>;

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
			stubs: { SubjectCreatorDialog: SubjectCreatorDialogStub, RecentSubjectsTable: true, CdxIcon: true },
		},
	} );
}

function findCreateSubjectButton( wrapper: VueWrapper ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxButton )
		.find( ( button ) => button.text().includes( 'neowiki-createsubject-button' ) );
}

/**
 * Where each card in the map goes, in the order the map shows them. A card given a url is itself
 * the link, so its own href is the destination.
 */
function mappedPages( wrapper: VueWrapper ): ( string | undefined )[] {
	return wrapper.findAllComponents( CdxCard ).map( ( card ) => card.attributes( 'href' ) );
}

function findCard( wrapper: VueWrapper, href: string ): VueWrapper | undefined {
	return wrapper.findAllComponents( CdxCard ).find( ( card ) => card.attributes( 'href' ) === href );
}

function precedes( earlier: Element, later: Element ): boolean {
	return earlier.compareDocumentPosition( later ) === Node.DOCUMENT_POSITION_FOLLOWING;
}

describe( 'OverviewPage', () => {
	beforeEach( () => {
		setupMwMock( {
			functions: [ 'util', 'config' ],
			config: { wgNeoWikiSubjectListAvailable: true },
		} );
		grantedRight = true;
		canCreateSubjectPageRef.value = false;
		pinia = createPinia();
		setActivePinia( pinia );
	} );

	it( 'offers to create a subject to a user who may', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( findCreateSubjectButton( wrapper ) ).toBeDefined();
	} );

	it( 'offers no way to create a subject to a user who may not', async () => {
		grantedRight = false;
		const wrapper = mountPage();
		await flushPromises();

		expect( findCreateSubjectButton( wrapper ) ).toBeUndefined();
	} );

	it( 'opens the creator from the create button', async () => {
		const wrapper = mountPage();
		await flushPromises();

		await findCreateSubjectButton( wrapper )!.trigger( 'click' );

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'hands the creator no page of its own to offer', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'hostPage' ) ).toBeNull();
	} );

	it( 'closes the creator when the dialog closes', async () => {
		const wrapper = mountPage();
		await flushPromises();
		await findCreateSubjectButton( wrapper )!.trigger( 'click' );

		wrapper.findComponent( SubjectCreatorDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'maps the other NeoWiki pages, and nothing the reader may not open', async () => {
		grantedRight = false;
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

	it( 'offers a card that leads to the schemas', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( findCard( wrapper, '/wiki/Special:Schemas' )!.text() )
			.toMatch( /neowiki-special-schemas.*neowiki-overview-schemas-description/s );
	} );

	it( 'shows the create button, then the map, then the recent Subjects', async () => {
		const wrapper = mountPage();
		await flushPromises();

		const createButton = findCreateSubjectButton( wrapper )!.element;
		const map = wrapper.findComponent( CdxCard ).element;
		const recentSubjects = wrapper.findComponent( RecentSubjectsTable ).element;

		expect( precedes( createButton, map ) ).toBe( true );
		expect( precedes( map, recentSubjects ) ).toBe( true );
	} );

	it( 'offers no Subject list on a wiki that cannot list Subjects', async () => {
		setupMwMock( {
			functions: [ 'util', 'config' ],
			config: { wgNeoWikiSubjectListAvailable: false },
		} );
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.findComponent( RecentSubjectsTable ).exists() ).toBe( false );
		expect( wrapper.html() ).not.toContain( 'Special:Subjects' );
	} );
} );
