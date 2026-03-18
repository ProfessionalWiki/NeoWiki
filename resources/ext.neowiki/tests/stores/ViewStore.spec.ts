import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useViewStore } from '@/stores/ViewStore.ts';
import { View } from '@/domain/View.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { InMemoryViewLookup } from '@/application/ViewLookup.ts';

vi.mock( '@/NeoWikiExtension.ts', () => ( {
	NeoWikiExtension: {
		getInstance: vi.fn(),
	},
} ) );

function newView( name: string ): View {
	return new View( name, 'Company', 'infobox', '', [], {} );
}

describe( 'ViewStore', () => {

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'returns undefined for an unknown view', () => {
		const store = useViewStore();

		expect( store.getView( 'NonExistent' ) ).toBeUndefined();
	} );

	it( 'returns a view after setView', () => {
		const store = useViewStore();
		const view = newView( 'FinancialOverview' );

		store.setView( 'FinancialOverview', view );

		expect( store.getView( 'FinancialOverview' ) ).toEqual( view );
	} );

	it( 'fetches and stores a view via fetchView', async () => {
		const view = newView( 'CompanyInfo' );
		const viewLookup = new InMemoryViewLookup( [ view ] );
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getViewLookup: () => viewLookup,
		} as unknown as NeoWikiExtension );

		const store = useViewStore();
		await store.fetchView( 'CompanyInfo' );

		expect( store.getView( 'CompanyInfo' ) ).toEqual( view );
	} );

	it( 'returns cached view from getOrFetchView without fetching again', async () => {
		const store = useViewStore();
		const view = newView( 'CachedView' );
		store.setView( 'CachedView', view );

		const getViewLookupSpy = vi.fn();
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getViewLookup: getViewLookupSpy,
		} as unknown as NeoWikiExtension );

		const result = await store.getOrFetchView( 'CachedView' );

		expect( result ).toEqual( view );
		expect( getViewLookupSpy ).not.toHaveBeenCalled();
	} );

} );
