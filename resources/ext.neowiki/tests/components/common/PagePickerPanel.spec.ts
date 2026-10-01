import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PagePickerPanel from '@/components/common/PagePickerPanel.vue';
import { CdxMenu } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { CREATE_PAGE, NO_RESULTS } from '@/composables/usePageSearch.ts';
import type { PinnedPage } from '@/composables/usePageSearch.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { Service } from '@/NeoWikiServices.ts';
import type { PageTitleSearch } from '@/domain/PageTitleSearch.ts';

const $i18n = createI18nMock();

const CdxMenuWithVModel = defineComponent( {
	name: 'CdxMenu',
	template: '<div />',
	props: {
		selected: { type: String, default: null },
		menuItems: { type: Array, default: () => [] },
		footer: { type: Object, default: undefined },
		expanded: { type: Boolean, default: false },
		showPending: { type: Boolean, default: false },
		showNoResultsSlot: { type: Boolean, default: false },
	},
	emits: [ 'update:selected' ],
	methods: {
		// The menu's keyboard handling is Codex's own; what matters here is which keys reach it.
		delegateKeyNavigation( event: KeyboardEvent ): boolean {
			delegatedKeys.push( event.key );
			return false;
		},
		getHighlightedMenuItem: (): null => null,
	},
} );

/** The keys the panel handed to the menu, in order. */
let delegatedKeys: string[] = [];

const CdxSearchInputWithVModel = {
	name: 'CdxSearchInput',
	template: '<input class="search" :value="modelValue" @keydown="$emit( \'keydown\', $event )">',
	props: [ 'modelValue', 'placeholder', 'ariaLabel' ],
	emits: [ 'update:modelValue', 'keydown' ],
};

/**
 * Two of them, so a shortcut can only be reported by picking the right one of the two. Worded as
 * the real host words them: the page each leads to, and under it which destination that is.
 */
const DENIED: PinnedPage[] = [
	{ label: 'The Night Watch', description: 'Not allowed', disabled: true,
		page: { pageId: null, title: '' } },
];

const PINNED: PinnedPage[] = [
	{ label: 'Rembrandt van Rijn', description: 'This page', page: { pageId: 7, title: 'Rembrandt van Rijn' } },
	{ label: 'The Night Watch', description: 'New page', page: { pageId: null, title: '' } },
];

describe( 'PagePickerPanel', () => {
	let mockPageTitleSearch: PageTitleSearch;

	function createWrapper( props: Record<string, unknown> = {} ): VueWrapper {
		return mount( PagePickerPanel, {
			props,
			global: {
				mocks: { $i18n },
				provide: { [ Service.PageTitleSearch ]: mockPageTitleSearch },
				stubs: { CdxMenu: CdxMenuWithVModel, CdxSearchInput: CdxSearchInputWithVModel },
			},
		} );
	}

	function menuItemsOf( wrapper: VueWrapper ): MenuItemData[] {
		return wrapper.findComponent( CdxMenu ).props( 'menuItems' ) as MenuItemData[];
	}

	function valuesOf( wrapper: VueWrapper ): unknown[] {
		return menuItemsOf( wrapper ).map( ( item ) => item.value );
	}

	function footerOf( wrapper: VueWrapper ): MenuItemData | undefined {
		return wrapper.findComponent( CdxMenu ).props( 'footer' ) as MenuItemData | undefined;
	}

	function lastSelection( wrapper: VueWrapper ): unknown {
		const events = wrapper.emitted( 'update:selected' ) ?? [];
		return events[ events.length - 1 ]?.[ 0 ];
	}

	async function search( wrapper: VueWrapper, text: string ): Promise<void> {
		wrapper.findComponent( CdxSearchInputWithVModel ).vm.$emit( 'update:modelValue', text );
		await flushPromises();
	}

	async function pick( wrapper: VueWrapper, value: string ): Promise<void> {
		wrapper.findComponent( CdxMenu ).vm.$emit( 'update:selected', value );
		await flushPromises();
	}

	function foundPages( pages: { pageId: number; title: string }[] ): void {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( pages );
	}

	/** Picking by position, so no test has to know how a menu value is spelled. */
	async function pickNth( wrapper: VueWrapper, index: number ): Promise<void> {
		await pick( wrapper, String( valuesOf( wrapper )[ index ] ) );
	}

	async function pressKey( wrapper: VueWrapper, key: string ): Promise<void> {
		await wrapper.find( 'input.search' ).trigger( 'keydown', { key } );
	}

	beforeEach( () => {
		setupMwMock();
		delegatedKeys = [];
		mockPageTitleSearch = { searchPageTitles: vi.fn().mockResolvedValue( [] ) };
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'searches page titles with the text typed', async () => {
		const wrapper = createWrapper();

		await search( wrapper, 'rembr' );

		expect( mockPageTitleSearch.searchPageTitles )
			.toHaveBeenCalledWith( 'rembr', expect.any( Number ) );
	} );

	it( 'reports the page picked out of the results', async () => {
		foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		await pickNth( wrapper, 0 );

		expect( lastSelection( wrapper ) ).toEqual( { pageId: 12, title: 'Amsterdam Museum' } );
	} );

	it( 'offers the typed text as a page to create, below the results', async () => {
		const wrapper = createWrapper();

		await search( wrapper, 'Vermeer' );

		expect( footerOf( wrapper )?.value ).toBe( CREATE_PAGE );
	} );

	it( 'reports the typed text as a page to create when that is picked', async () => {
		const wrapper = createWrapper();
		await search( wrapper, 'Vermeer' );

		await pick( wrapper, CREATE_PAGE );

		expect( lastSelection( wrapper ) ).toEqual( { pageId: null, title: 'Vermeer' } );
	} );

	it( 'offers no page to create where the host allows only pages that exist', async () => {
		const wrapper = createWrapper( { existingPagesOnly: true } );

		await search( wrapper, 'Vermeer' );

		expect( footerOf( wrapper ) ).toBeUndefined();
	} );

	describe( 'the shortcuts its host pinned', () => {
		it( 'offers them, named as its host worded them, before anything has been typed', () => {
			expect( menuItemsOf( createWrapper( { pinnedPages: PINNED } ) ).map( ( item ) => item.label ) )
				.toEqual( [ 'Rembrandt van Rijn', 'The Night Watch' ] );
		} );

		// Whatever the host puts under the name, which is its business and not this panel's.
		it( 'carries what its host said of each', () => {
			expect( menuItemsOf( createWrapper( { pinnedPages: PINNED } ) )
				.map( ( item ) => item.description ) )
				.toEqual( [ 'This page', 'New page' ] );
		} );

		it( 'reports the first one when the first one is picked', async () => {
			const wrapper = createWrapper( { pinnedPages: PINNED } );

			await pickNth( wrapper, 0 );

			expect( lastSelection( wrapper ) ).toEqual( { pageId: 7, title: 'Rembrandt van Rijn' } );
		} );

		it( 'reports the second one when the second one is picked', async () => {
			const wrapper = createWrapper( { pinnedPages: PINNED } );

			await pickNth( wrapper, 1 );

			expect( lastSelection( wrapper ) ).toEqual( { pageId: null, title: '' } );
		} );

		/**
		 * Shown rather than left out, so the option is accounted for: its host says in the
		 * description why it cannot be taken.
		 */
		it( 'offers one its host disabled, unpickable', () => {
			expect( menuItemsOf( createWrapper( { pinnedPages: DENIED } ) )[ 0 ].disabled ).toBe( true );
		} );

		it( 'reports nothing when a disabled one is picked all the same', async () => {
			const wrapper = createWrapper( { pinnedPages: DENIED } );

			await pickNth( wrapper, 0 );

			expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
		} );

		// They are shortcuts past searching, so searching is what retires them.
		it( 'drops them once something has been typed', async () => {
			foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
			const wrapper = createWrapper( { pinnedPages: PINNED } );

			await search( wrapper, 'amster' );

			expect( menuItemsOf( wrapper ).map( ( item ) => item.label ) )
				.toEqual( [ 'Amsterdam Museum' ] );
		} );
	} );

	describe( 'a search that finds nothing', () => {
		/**
		 * The menu has no field of its own to ask whether anything was typed, so without this it
		 * answers a fruitless search with an empty box and no words at all - which for a host that
		 * allows only existing pages has no create option below it either.
		 */
		it( 'says so where no page may be created', async () => {
			const wrapper = createWrapper( { existingPagesOnly: true } );

			await search( wrapper, 'zzzz' );

			expect( wrapper.findComponent( CdxMenu ).props( 'showNoResultsSlot' ) ).toBe( true );
		} );

		it( 'says nothing before a search has been made', () => {
			expect( createWrapper( { existingPagesOnly: true } )
				.findComponent( CdxMenu ).props( 'showNoResultsSlot' ) ).toBe( false );
		} );

		it( 'says so as an entry where a page may be created, the create option being below it', async () => {
			const wrapper = createWrapper();

			await search( wrapper, 'zzzz' );

			expect( valuesOf( wrapper ) ).toEqual( [ NO_RESULTS ] );
		} );
	} );

	/**
	 * A search the user has moved on from must not fill the list back in when it lands. The panel
	 * clears as soon as the field empties, and the answer already on its way has to be retired with
	 * it, or results nobody asked for appear under an empty field, pickable.
	 */
	it( 'ignores the answer to a search the field has been cleared of', async () => {
		let answer!: ( pages: unknown[] ) => void;
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockReturnValue(
			new Promise( ( resolve ) => {
				answer = resolve;
			} ),
		);
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		await search( wrapper, '' );
		answer( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
		await flushPromises();

		expect( menuItemsOf( wrapper ) ).toEqual( [] );
	} );

	/**
	 * Codex holds the row the arrow keys reached as a snapshot and never re-resolves it against the
	 * list, so an Enter landing after the results changed under the highlight would otherwise
	 * report a page that is no longer being offered - one the user never saw.
	 */
	it( 'reports nothing for a page the list is no longer offering', async () => {
		foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		await pick( wrapper, '999' );

		expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
	} );

	describe( 'the keys it hands to the menu', () => {
		it( 'gives it the arrow keys, which move the highlight', async () => {
			const wrapper = createWrapper();

			await pressKey( wrapper, 'ArrowDown' );

			expect( delegatedKeys ).toEqual( [ 'ArrowDown' ] );
		} );

		// A page title may contain a space, and escape belongs to whatever opened the panel.
		it( 'keeps space and escape for the field', async () => {
			const wrapper = createWrapper();

			await pressKey( wrapper, ' ' );
			await pressKey( wrapper, 'Escape' );

			expect( delegatedKeys ).toEqual( [] );
		} );

		// The caret is in the field, not in the list, so the caret keys are the field's.
		it( 'keeps home and end for the caret', async () => {
			const wrapper = createWrapper();

			await pressKey( wrapper, 'Home' );
			await pressKey( wrapper, 'End' );

			expect( delegatedKeys ).toEqual( [] );
		} );

		/**
		 * Picking closes the panel, which hands the focus back to the button that opened it, and
		 * CdxButton clicks itself on the Enter keyup that then lands on it - which would open the
		 * panel straight back up. So Enter picks on its own keyup instead.
		 */
		it( 'takes enter on the keyup rather than the keydown', async () => {
			const wrapper = createWrapper();

			await pressKey( wrapper, 'Enter' );

			expect( delegatedKeys ).toEqual( [] );

			await wrapper.find( 'input.search' ).trigger( 'keyup', { key: 'Enter' } );

			expect( delegatedKeys ).toEqual( [ 'Enter' ] );
		} );
	} );

	/**
	 * Against the real menu, which is what holds the highlight: the focus stays in the search field
	 * while the arrow keys move through the list, so the field is what has to name the row they
	 * reached. Stubbed out, there is no highlighted row to name.
	 */
	describe( 'against the real CdxMenu', () => {
		function mountWithRealMenu( props: Record<string, unknown> = {} ): VueWrapper {
			return mount( PagePickerPanel, {
				props,
				global: {
					mocks: { $i18n },
					provide: { [ Service.PageTitleSearch ]: mockPageTitleSearch },
					stubs: { CdxSearchInput: CdxSearchInputWithVModel },
				},
				attachTo: document.body,
			} );
		}

		it( 'names the row the arrow keys reached', async () => {
			foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
			const wrapper = mountWithRealMenu();
			await search( wrapper, 'amster' );

			await wrapper.find( 'input.search' ).trigger( 'keydown', { key: 'ArrowDown' } );
			await flushPromises();

			const highlighted = wrapper.find( '.cdx-menu-item--highlighted' );
			expect( highlighted.exists() ).toBe( true );
			expect( wrapper.find( 'input.search' ).attributes( 'aria-activedescendant' ) )
				.toBe( highlighted.attributes( 'id' ) );

			wrapper.unmount();
		} );

		it( 'names no row before the arrow keys have reached one', async () => {
			foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
			const wrapper = mountWithRealMenu();

			await search( wrapper, 'amster' );

			expect( wrapper.find( 'input.search' ).attributes( 'aria-activedescendant' ) )
				.toBeUndefined();

			wrapper.unmount();
		} );
	} );

	/**
	 * For a host whose destination is fixed at a page to create and open only as to what that page
	 * is called: offering any other page would unfix what the host fixed, so none is looked for.
	 */
	describe( 'asked for nothing but a page to create', () => {
		it( 'offers that option alone', async () => {
			foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
			const wrapper = createWrapper( { newPageOnly: true, pinnedPages: PINNED } );

			await search( wrapper, 'amster' );

			expect( menuItemsOf( wrapper ) ).toEqual( [] );
			expect( footerOf( wrapper )?.value ).toBe( CREATE_PAGE );
		} );

		// Not even a shortcut: a host that fixed the destination has no second one to offer, and a
		// shortcut would be pickable before anything is typed.
		it( 'offers no shortcut its host pinned either', () => {
			const wrapper = createWrapper( { newPageOnly: true, pinnedPages: PINNED } );

			expect( menuItemsOf( wrapper ) ).toEqual( [] );
		} );

		it( 'looks for no page at all', async () => {
			const wrapper = createWrapper( { newPageOnly: true } );

			await search( wrapper, 'amster' );

			expect( mockPageTitleSearch.searchPageTitles ).not.toHaveBeenCalled();
		} );

		it( 'reports the title typed as the page to create', async () => {
			const wrapper = createWrapper( { newPageOnly: true } );
			await search( wrapper, 'Ada Lovelace' );

			await pick( wrapper, CREATE_PAGE );

			expect( lastSelection( wrapper ) ).toEqual( { pageId: null, title: 'Ada Lovelace' } );
		} );
	} );

	it( 'leaves out the page its host excluded', async () => {
		foundPages( [
			{ pageId: 12, title: 'Amsterdam Museum' },
			{ pageId: 7, title: 'Rembrandt van Rijn' },
			{ pageId: 34, title: 'Amsterdam' },
		] );
		const wrapper = createWrapper( { excludedPageId: 7 } );

		await search( wrapper, 'a' );

		expect( menuItemsOf( wrapper ).map( ( item ) => item.label ) )
			.toEqual( [ 'Amsterdam Museum', 'Amsterdam' ] );
	} );

	// It reports a choice and its host closes it, so there is no selection to keep - and the same
	// entry picked twice running has to be reported twice.
	it( 'holds on to no selection of its own', async () => {
		foundPages( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		await pickNth( wrapper, 0 );

		expect( wrapper.findComponent( CdxMenu ).props( 'selected' ) ).toBeNull();
	} );

	it( 'reports nothing for an entry that stands for no page', async () => {
		const wrapper = createWrapper();
		await search( wrapper, 'zzzz' );

		await pick( wrapper, NO_RESULTS );

		expect( wrapper.emitted( 'update:selected' ) ).toBeUndefined();
	} );
} );
