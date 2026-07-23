import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import LayoutsPage from '@/components/LayoutsPage/LayoutsPage.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

interface LayoutSummary {
	name: string;
	schema: string;
	type: string;
	description: string;
	ruleCount: number;
}

const canCreateLayoutsRef = ref( false );
const canEditLayoutRef = ref( false );
const checkCreatePermissionMock = vi.fn();
const checkEditPermissionMock = vi.fn();

let layoutsResponse: { layouts: LayoutSummary[]; nextCursor: string | null } = { layouts: [], nextCursor: null };

vi.mock( '@/composables/useLayoutPermissions.ts', () => ( {
	useLayoutPermissions: () => ( {
		canCreateLayouts: canCreateLayoutsRef,
		canEditLayout: canEditLayoutRef,
		checkCreatePermission: checkCreatePermissionMock,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

vi.mock( '@/stores/LayoutStore.ts', () => ( {
	useLayoutStore: () => ( {
		fetchLayout: vi.fn(),
		getLayout: vi.fn(),
		saveLayout: vi.fn(),
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
			mocks: { $i18n: createI18nMock() },
			stubs: {
				LayoutCreatorDialog: true,
				LayoutEditorDialog: true,
				EditSummary: true,
				I18nSlot: true,
				CdxIcon: true,
			},
		},
	} );
}

describe( 'LayoutsPage', () => {
	beforeEach( () => {
		canCreateLayoutsRef.value = false;
		canEditLayoutRef.value = false;
		checkCreatePermissionMock.mockClear();
		checkEditPermissionMock.mockClear();
		layoutsResponse = { layouts: [], nextCursor: null };
	} );

	it( 'disables next when a full page ends the listing', async () => {
		// A listing that ends exactly on a page boundary returns a full page with a null cursor.
		// CdxTable's indeterminate mode would keep next enabled (its heuristic is a short page), so
		// the component must switch the table to a known total.
		const wrapper = mountComponent( fullPage(), null );
		await flushPromises();

		const nextButton = wrapper.find( '.cdx-table-pager button[aria-label="Next page"]' );

		expect( nextButton.attributes( 'disabled' ) ).toBeDefined();
		expect( wrapper.text() ).toContain( 'of 10' );
	} );

	it( 'keeps next enabled while the listing continues', async () => {
		// A full page with a non-null cursor means more rows follow. The component must leave
		// totalRows undefined so CdxTable stays in its indeterminate mode (next enabled, "of many"
		// label); a known total here would wrongly disable next and hide the remaining pages.
		const wrapper = mountComponent( fullPage(), 'next-page-cursor' );
		await flushPromises();

		const nextButton = wrapper.find( '.cdx-table-pager button[aria-label="Next page"]' );

		expect( nextButton.attributes( 'disabled' ) ).toBeUndefined();
		expect( wrapper.text() ).toContain( 'of many' );
	} );
} );
