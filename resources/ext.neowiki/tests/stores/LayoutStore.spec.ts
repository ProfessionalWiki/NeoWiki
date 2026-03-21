import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { Layout } from '@/domain/Layout.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup.ts';

vi.mock( '@/NeoWikiExtension.ts', () => ( {
	NeoWikiExtension: {
		getInstance: vi.fn(),
	},
} ) );

function newLayout( name: string ): Layout {
	return new Layout( name, 'Company', 'infobox', '', [], {} );
}

describe( 'LayoutStore', () => {

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'returns undefined for an unknown layout', () => {
		const store = useLayoutStore();

		expect( store.getLayout( 'NonExistent' ) ).toBeUndefined();
	} );

	it( 'returns a layout after setLayout', () => {
		const store = useLayoutStore();
		const layout = newLayout( 'FinancialOverview' );

		store.setLayout( 'FinancialOverview', layout );

		expect( store.getLayout( 'FinancialOverview' ) ).toEqual( layout );
	} );

	it( 'fetches and stores a layout via fetchLayout', async () => {
		const layout = newLayout( 'CompanyInfo' );
		const layoutLookup = new InMemoryLayoutLookup( [ layout ] );
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getLayoutLookup: () => layoutLookup,
		} as unknown as NeoWikiExtension );

		const store = useLayoutStore();
		await store.fetchLayout( 'CompanyInfo' );

		expect( store.getLayout( 'CompanyInfo' ) ).toEqual( layout );
	} );

	it( 'returns cached layout from getOrFetchLayout without fetching again', async () => {
		const store = useLayoutStore();
		const layout = newLayout( 'CachedLayout' );
		store.setLayout( 'CachedLayout', layout );

		const getLayoutLookupSpy = vi.fn();
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getLayoutLookup: getLayoutLookupSpy,
		} as unknown as NeoWikiExtension );

		const result = await store.getOrFetchLayout( 'CachedLayout' );

		expect( result ).toEqual( layout );
		expect( getLayoutLookupSpy ).not.toHaveBeenCalled();
	} );

} );
