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
			getLayoutRepository: () => layoutLookup,
		} as unknown as NeoWikiExtension );

		const store = useLayoutStore();
		await store.fetchLayout( 'CompanyInfo' );

		expect( store.getLayout( 'CompanyInfo' ) ).toEqual( layout );
	} );

	it( 'discards a fetch that a save overtook', async () => {
		let resolveFetch!: ( layout: Layout ) => void;
		const pending = new Promise<Layout>( ( resolve ) => {
			resolveFetch = resolve;
		} );
		const saved = new Layout( 'CompanyInfo', 'Company', 'infobox', 'saved', [], {} );
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getLayoutRepository: () => ( {
				getLayout: () => pending,
				saveLayout: vi.fn().mockResolvedValue( undefined ),
			} ),
		} as unknown as NeoWikiExtension );
		const store = useLayoutStore();

		const request = store.fetchLayout( 'CompanyInfo' );
		await store.saveLayout( saved );
		resolveFetch( new Layout( 'CompanyInfo', 'Company', 'infobox', 'pre-save', [], {} ) );
		await request;

		expect( store.getLayout( 'CompanyInfo' ) ).toStrictEqual( saved );
	} );

	it( 'does not write through when the repository rejects the save', async () => {
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getLayoutRepository: () => ( {
				saveLayout: vi.fn().mockRejectedValue( new Error( 'save failed' ) ),
			} ),
		} as unknown as NeoWikiExtension );
		const store = useLayoutStore();

		await expect( store.saveLayout( newLayout( 'CompanyInfo' ) ) ).rejects.toThrow( 'save failed' );

		expect( store.getLayout( 'CompanyInfo' ) ).toBeUndefined();
	} );

	it( 'removes the layout from the registry', () => {
		const store = useLayoutStore();
		store.setLayout( 'CompanyInfo', newLayout( 'CompanyInfo' ) );

		store.removeLayout( 'CompanyInfo' );

		expect( store.getLayout( 'CompanyInfo' ) ).toBeUndefined();
	} );

} );
