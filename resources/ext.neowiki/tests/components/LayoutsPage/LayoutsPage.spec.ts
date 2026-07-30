import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import LayoutsPage from '@/components/LayoutsPage/LayoutsPage.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import LayoutEditorDialog from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import { createI18nMock, findNextPageButton, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxButton } from '@wikimedia/codex';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { Layout } from '@/domain/Layout.ts';

interface LayoutSummary {
	name: string;
	schema: string;
	type: string;
	description: string;
	ruleCount: number;
}

function newLayout( name: string ): Layout {
	return new Layout( name, 'Company', 'infobox', '', [], {} );
}

const canCreateLayoutsRef = ref( false );
const canEditLayoutRef = ref( false );
const checkCreatePermissionMock = vi.fn();
const checkEditPermissionMock = vi.fn();

let layoutsResponse: { layouts: LayoutSummary[]; nextCursor: string | null } = { layouts: [], nextCursor: null };
let pinia: ReturnType<typeof createPinia>;
let layoutStore: ReturnType<typeof useLayoutStore>;

vi.mock( '@/composables/useLayoutPermissions.ts', () => ( {
	useLayoutPermissions: () => ( {
		canCreateLayouts: canCreateLayoutsRef,
		canEditLayout: canEditLayoutRef,
		checkCreatePermission: checkCreatePermissionMock,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

// The store is real (backed by Pinia). Most tests never drive the editor path
// (fetchLayout/getLayout), so those actions never reach the network here; the test that does
// drive it overrides fetchLayout directly on the store instance instead of hitting the network.

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

function fullPage(): LayoutSummary[] {
	return Array.from( { length: 10 }, ( _value, index ) => ( {
		name: `Layout${ index }`,
		schema: 'Person',
		type: 'infobox',
		description: '',
		ruleCount: 0,
	} ) );
}

function mountComponent( summaries: LayoutSummary[] = [], nextCursor: string | null = null ): VueWrapper {
	layoutsResponse = {
		layouts: summaries,
		nextCursor: nextCursor,
	};
	setupMwMock( { functions: [ 'msg', 'util', 'message', 'notify' ] } );

	return mount( LayoutsPage, {
		global: {
			plugins: [ pinia ],
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
	ruleCount: 0,
};

describe( 'LayoutsPage', () => {
	beforeEach( () => {
		canCreateLayoutsRef.value = false;
		canEditLayoutRef.value = false;
		checkCreatePermissionMock.mockClear();
		checkEditPermissionMock.mockClear();
		layoutsResponse = { layouts: [], nextCursor: null };

		pinia = createPinia();
		setActivePinia( pinia );
		layoutStore = useLayoutStore();
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
		expect( dialog.props( 'typeLabel' ) ).toBe( 'neowiki-layout-noun' );
	} );

	it( 'removes the deleted layout from the store and refetches the list', async () => {
		canEditLayoutRef.value = true;
		const wrapper = mountComponent( [ sampleLayout ] );
		await flushPromises();

		layoutStore.setLayout( 'CompanyOverview', newLayout( 'CompanyOverview' ) );

		await findDeleteButtons( wrapper )[ 0 ].trigger( 'click' );

		// A different fixture than the initial mount proves the @deleted handler
		// actually refetched rather than just closing the dialog.
		layoutsResponse = { layouts: [
			{ name: 'Warehouse', schema: 'Company', type: 'infobox', description: '', ruleCount: 0 },
		], nextCursor: null };

		wrapper.findComponent( DeletePageDialog ).vm.$emit( 'deleted' );
		await flushPromises();

		expect( layoutStore.getLayout( 'CompanyOverview' ) ).toBeUndefined();
		expect( wrapper.text() ).toContain( 'Warehouse' );
		expect( wrapper.find( 'a[href="/wiki/Layout:CompanyOverview"]' ).exists() ).toBe( false );
	} );

	it( 'does not open the editor when the epoch guard discarded the fetchLayout write-back', async () => {
		canEditLayoutRef.value = true;
		// Simulates the epoch guard discarding fetchLayout's write-back (a mutation landed
		// mid-flight): the fetch resolves but never seeds the store, so getLayout keeps
		// returning undefined for this layout.
		layoutStore.fetchLayout = vi.fn().mockResolvedValue( undefined );
		const wrapper = mountComponent( [ sampleLayout ] );
		await flushPromises();

		await findEditButtons( wrapper )[ 0 ].trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( LayoutEditorDialog ).exists() ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-layouts-edit-stale-error', { type: 'error' } );
	} );

	it( 'disables next when a full page ends the listing', async () => {
		// A listing that ends exactly on a page boundary returns a full page with a null cursor.
		// CdxTable's indeterminate mode would keep next enabled (its heuristic is a short page), so
		// the component must switch the table to a known total.
		const wrapper = mountComponent( fullPage(), null );
		await flushPromises();

		const nextButton = findNextPageButton( wrapper );

		expect( nextButton.attributes( 'disabled' ) ).toBeDefined();
		expect( wrapper.text() ).toContain( 'of 10' );
	} );

	it( 'keeps next enabled while the listing continues', async () => {
		// A full page with a non-null cursor means more rows follow. The component must leave
		// totalRows undefined so CdxTable stays in its indeterminate mode (next enabled, "of many"
		// label); a known total here would wrongly disable next and hide the remaining pages.
		const wrapper = mountComponent( fullPage(), 'next-page-cursor' );
		await flushPromises();

		const nextButton = findNextPageButton( wrapper );

		expect( nextButton.attributes( 'disabled' ) ).toBeUndefined();
		expect( wrapper.text() ).toContain( 'of many' );
	} );
} );
