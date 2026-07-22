import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import LayoutsPage from '@/components/LayoutsPage/LayoutsPage.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton } from '@wikimedia/codex';

interface LayoutSummary {
	name: string;
	schema: string;
	type: string;
	description: string;
}

const canCreateLayoutsRef = ref( false );
const canEditLayoutRef = ref( false );
const checkCreatePermissionMock = vi.fn();
const checkEditPermissionMock = vi.fn();

let layoutsResponse: { layouts: LayoutSummary[]; totalRows: number } = { layouts: [], totalRows: 0 };

vi.mock( '@/composables/useLayoutPermissions.ts', () => ( {
	useLayoutPermissions: () => ( {
		canCreateLayouts: canCreateLayoutsRef,
		canEditLayout: canEditLayoutRef,
		checkCreatePermission: checkCreatePermissionMock,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

// The store is only exercised by the editor path, not by the deletion flow under test.
vi.mock( '@/stores/LayoutStore.ts', () => ( {
	useLayoutStore: () => ( {
		fetchLayout: vi.fn(),
		getLayout: vi.fn(),
	} ),
} ) );

vi.mock( '@/NeoWikiExtension.ts', () => ( {
	NeoWikiExtension: {
		getInstance: () => ( {
			getMediaWiki: () => ( {
				util: { wikiScript: () => '/rest.php' },
			} ),
			newHttpClient: () => ( {
				get: vi.fn().mockResolvedValue( {
					ok: true,
					json: () => Promise.resolve( layoutsResponse ),
				} ),
			} ),
		} ),
	},
} ) );

function findEditButtons( wrapper: VueWrapper ): VueWrapper[] {
	return wrapper.findAllComponents( CdxButton )
		.filter( ( btn ) => btn.attributes( 'aria-label' ) === 'neowiki-edit-layout' );
}

function findDeleteButtons( wrapper: VueWrapper ): VueWrapper[] {
	return wrapper.findAllComponents( CdxButton )
		.filter( ( btn ) => btn.attributes( 'aria-label' ) === 'neowiki-layout-delete' );
}

function mountComponent( summaries: LayoutSummary[] = [] ): VueWrapper {
	layoutsResponse = { layouts: summaries, totalRows: summaries.length };
	setupMwMock( { functions: [ 'msg', 'util', 'message', 'notify' ] } );

	return mount( LayoutsPage, {
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: {
				LayoutCreatorDialog: true,
				LayoutEditorDialog: true,
				DeletePageDialog: true,
				CdxIcon: true,
			},
		},
	} );
}

const sampleLayout: LayoutSummary = {
	name: 'CompanyOverview',
	schema: 'Company',
	type: 'infobox',
	description: 'Overview',
};

describe( 'LayoutsPage', () => {
	beforeEach( () => {
		canCreateLayoutsRef.value = false;
		canEditLayoutRef.value = false;
		checkCreatePermissionMock.mockClear();
		checkEditPermissionMock.mockClear();
		layoutsResponse = { layouts: [], totalRows: 0 };
	} );

	it( 'links each layout name to its Layout page', async () => {
		const wrapper = mountComponent( [ sampleLayout ] );
		await flushPromises();

		const link = wrapper.find( 'a[href="/wiki/Layout:CompanyOverview"]' );
		expect( link.exists() ).toBe( true );
		expect( link.text() ).toBe( 'CompanyOverview' );
	} );

	it( 'shows edit and delete buttons when the user may edit layouts', async () => {
		canEditLayoutRef.value = true;
		const wrapper = mountComponent( [ sampleLayout ] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 1 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 1 );
	} );

	it( 'hides edit and delete buttons without edit permission', async () => {
		canEditLayoutRef.value = false;
		const wrapper = mountComponent( [ sampleLayout ] );
		await flushPromises();

		expect( findEditButtons( wrapper ) ).toHaveLength( 0 );
		expect( findDeleteButtons( wrapper ) ).toHaveLength( 0 );
	} );

	it( 'opens the delete confirmation for the clicked layout', async () => {
		canEditLayoutRef.value = true;
		const wrapper = mountComponent( [ sampleLayout ] );
		await flushPromises();

		await findDeleteButtons( wrapper )[ 0 ].trigger( 'click' );

		const dialog = wrapper.findComponent( DeletePageDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'pageTitle' ) ).toBe( 'Layout:CompanyOverview' );
		expect( dialog.props( 'displayName' ) ).toBe( 'CompanyOverview' );
	} );
} );
