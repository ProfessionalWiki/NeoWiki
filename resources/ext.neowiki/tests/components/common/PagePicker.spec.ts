import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PagePicker from '@/components/common/PagePicker.vue';
import { CdxLookup } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { Service } from '@/NeoWikiServices.ts';
import type { PageTitleSearch } from '@/domain/PageTitleSearch.ts';

const $i18n = createI18nMock();

const CdxLookupWithVModel = defineComponent( {
	template: '<div />',
	props: {
		selected: { type: String, default: null },
		inputValue: { type: [ String, Number ], default: '' },
		menuItems: { type: Array, default: () => [] },
		placeholder: { type: String, default: '' },
		ariaLabel: { type: String, default: undefined },
	},
	emits: [ 'update:selected', 'update:input-value', 'input' ],
} );

describe( 'PagePicker', () => {
	let mockPageTitleSearch: PageTitleSearch;

	function createWrapper( props: Record<string, unknown> = {} ): VueWrapper {
		return mount( PagePicker, {
			props,
			global: {
				mocks: { $i18n },
				provide: { [ Service.PageTitleSearch ]: mockPageTitleSearch },
				stubs: { CdxLookup: CdxLookupWithVModel },
			},
		} );
	}

	function menuItemsOf( wrapper: VueWrapper ): MenuItemData[] {
		return wrapper.findComponent( CdxLookup ).props( 'menuItems' ) as MenuItemData[];
	}

	function valuesOf( wrapper: VueWrapper ): unknown[] {
		return menuItemsOf( wrapper ).map( ( item ) => item.value );
	}

	function lastSelection( wrapper: VueWrapper ): unknown {
		const events = wrapper.emitted( 'update:selected' ) ?? [];
		return events[ events.length - 1 ];
	}

	// Only the v-model value: CdxLookup's `input` event is deliberately not listened to, because it
	// re-fires after a selection carrying the text typed before it.
	async function search( wrapper: VueWrapper, text: string ): Promise<void> {
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:input-value', text );
		await flushPromises();
	}

	beforeEach( () => {
		setupMwMock();
		mockPageTitleSearch = { searchPageTitles: vi.fn().mockResolvedValue( [] ) };
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'searches page titles with the text typed', async () => {
		const wrapper = createWrapper();

		await search( wrapper, 'amster' );

		expect( mockPageTitleSearch.searchPageTitles ).toHaveBeenCalledWith( 'amster', 10 );
	} );

	it( 'carries each result page id as its menu value, so no title resolution is needed', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
			{ pageId: 34, title: 'Amsterdam' },
		] );
		const wrapper = createWrapper();

		await search( wrapper, 'amster' );

		expect( menuItemsOf( wrapper ).slice( 0, 2 ).map( ( item ) => [ item.value, item.label ] ) ).toEqual( [
			[ '12', 'Amsterdam Museum' ],
			[ '34', 'Amsterdam' ],
		] );
	} );

	it( 'leaves out excluded pages, such as the one the subject is already on', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
			{ pageId: 34, title: 'Amsterdam' },
		] );
		const wrapper = createWrapper( { excludedPageId: 12 } );

		await search( wrapper, 'amster' );

		expect( valuesOf( wrapper ) ).toEqual( [ '34', '__create__' ] );
	} );

	it( 'offers the create option before anything is typed, so the menu opens on focus', () => {
		expect( valuesOf( createWrapper() ) ).toEqual( [ '__create__' ] );
	} );

	it( 'leaves the create option unpickable while nothing has been typed', () => {
		expect( menuItemsOf( createWrapper() )[ 0 ].disabled ).toBe( true );
	} );

	it( 'names the create option after the typed title once there is one', async () => {
		const wrapper = createWrapper();

		await search( wrapper, 'Rembrandt' );

		const createItem = menuItemsOf( wrapper )[ menuItemsOf( wrapper ).length - 1 ] as MenuItemData;
		expect( createItem.label ).toBe( 'neowiki-page-picker-create-namedRembrandt' );
		expect( createItem.disabled ).toBe( false );
	} );

	it( 'keeps the create option last, under the results', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
		] );
		const wrapper = createWrapper();

		await search( wrapper, 'amster' );

		expect( valuesOf( wrapper ) ).toEqual( [ '12', '__create__' ] );
	} );

	it( 'says nothing was found only once a search has come back empty', async () => {
		const wrapper = createWrapper();
		expect( valuesOf( wrapper ) ).not.toContain( '__no_results__' );

		await search( wrapper, 'nothing here' );

		expect( valuesOf( wrapper ) ).toEqual( [ '__no_results__', '__create__' ] );
	} );

	it( 'reports the picked page by id and title', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
		] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '12' );
		await flushPromises();

		expect( lastSelection( wrapper ) ).toEqual( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
	} );

	it( 'reports a page that does not exist yet with no id', async () => {
		const wrapper = createWrapper();
		await search( wrapper, 'Rembrandt' );

		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '__create__' );
		await flushPromises();

		expect( lastSelection( wrapper ) ).toEqual( [ { pageId: null, title: 'Rembrandt' } ] );
	} );

	it( 'reports nothing when the unpickable no-results item is selected', async () => {
		const wrapper = createWrapper();
		await search( wrapper, 'nothing here' );
		const before = wrapper.emitted( 'update:selected' )?.length ?? 0;

		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '__no_results__' );
		await flushPromises();

		expect( wrapper.emitted( 'update:selected' )?.length ?? 0 ).toBe( before );
	} );

	it( 'does not offer to create a page named after the result just picked', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
		] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '12' );
		// Codex writes the picked item's label into the field, which must not read as typed text.
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'Amsterdam Museum' );
		await flushPromises();

		expect( menuItemsOf( wrapper )[ menuItemsOf( wrapper ).length - 1 ]?.label ).toBe( 'neowiki-page-picker-create-hint' );
	} );

	it( 'drops a chosen new-page title once the text is edited', async () => {
		// Codex clears its own selection when the text moves away from a picked item, but a picked
		// create option leaves it holding none - so without this the host would keep the old title.
		const wrapper = createWrapper();
		await search( wrapper, 'Rembrandt van Rjin' );
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '__create__' );
		await flushPromises();

		await search( wrapper, 'Rembrandt van Rijn' );

		expect( lastSelection( wrapper ) ).toEqual( [ null ] );
	} );

	it( 'drops a chosen existing page once the text is edited', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
		] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '12' );
		await flushPromises();

		await search( wrapper, 'amsterd' );

		expect( lastSelection( wrapper ) ).toEqual( [ null ] );
	} );

	it( 'keeps the choice when the text is only the label Codex wrote for it', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
		] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:selected', '12' );
		await flushPromises();

		// Codex writes the picked item's label into the field; that is not the user editing it.
		await search( wrapper, 'Amsterdam Museum' );

		expect( lastSelection( wrapper ) ).toEqual( [ { pageId: 12, title: 'Amsterdam Museum' } ] );
	} );

	it( 'ignores a search response overtaken by a newer one', async () => {
		let resolveFirst: ( value: unknown ) => void = () => undefined;
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> )
			.mockImplementationOnce( () => new Promise( ( resolve ) => {
				resolveFirst = resolve;
			} ) )
			.mockResolvedValueOnce( [ { pageId: 34, title: 'Second' } ] );

		const wrapper = createWrapper();

		// Flushed between the two, or Vue would batch both text changes into a single watcher run and
		// only one search would ever start.
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'first' );
		await flushPromises();

		await search( wrapper, 'second' );

		resolveFirst( [ { pageId: 12, title: 'First' } ] );
		await flushPromises();

		expect( valuesOf( wrapper ) ).toEqual( [ '34', '__create__' ] );
	} );

	it( 'clears the results and reports nothing when the field is emptied', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> ).mockResolvedValue( [
			{ pageId: 12, title: 'Amsterdam Museum' },
		] );
		const wrapper = createWrapper();
		await search( wrapper, 'amster' );

		await search( wrapper, '' );

		expect( valuesOf( wrapper ) ).toEqual( [ '__create__' ] );
		expect( lastSelection( wrapper ) ).toEqual( [ null ] );
	} );

	it( 'shows no results when the search fails', async () => {
		( mockPageTitleSearch.searchPageTitles as ReturnType<typeof vi.fn> )
			.mockRejectedValue( new Error( 'network' ) );
		const wrapper = createWrapper();

		await search( wrapper, 'amster' );

		expect( valuesOf( wrapper ) ).toEqual( [ '__no_results__', '__create__' ] );
	} );

} );

// The stub above cannot show this: Codex writes the picked item's label into the field and reacts to
// that change itself, and the ordering of those emits is exactly what broke selection in the browser.
// So this one case drives the real component, as SubjectPicker.spec does for its own Codex quirk.
describe( 'PagePicker against the real CdxLookup', () => {
	let mockPageTitleSearch: PageTitleSearch;

	beforeEach( () => {
		setupMwMock();
		mockPageTitleSearch = {
			searchPageTitles: vi.fn().mockResolvedValue( [ { pageId: 55, title: 'ACME Inc' } ] ),
		};
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'keeps reporting the picked page when its title differs from what was typed', async () => {
		const wrapper = mount( PagePicker, {
			props: {},
			global: {
				mocks: { $i18n },
				provide: { [ Service.PageTitleSearch ]: mockPageTitleSearch },
			},
			attachTo: document.body,
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'ACME' );
		await flushPromises();

		const option = wrapper.findAll( '.cdx-menu-item' )
			.find( ( item ) => item.text().includes( 'ACME Inc' ) );
		expect( option ).toBeDefined();

		await option!.trigger( 'click' );
		await flushPromises();

		const events = wrapper.emitted( 'update:selected' ) ?? [];
		expect( events[ events.length - 1 ] ).toEqual( [ { pageId: 55, title: 'ACME Inc' } ] );

		wrapper.unmount();
	} );
} );
