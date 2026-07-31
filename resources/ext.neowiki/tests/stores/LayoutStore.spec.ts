import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { Layout } from '@/domain/Layout.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

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

	it( 'writes the saved layout into the registry', async () => {
		const saved = newLayout( 'CompanyInfo' );
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getLayoutRepository: () => ( {
				saveLayout: vi.fn().mockResolvedValue( undefined ),
			} ),
		} as unknown as NeoWikiExtension );
		const store = useLayoutStore();

		await store.saveLayout( saved );

		expect( store.getLayout( 'CompanyInfo' ) ).toStrictEqual( saved );
	} );

	it( 'bumps the mutation epoch once the backend acknowledges the save', async () => {
		// The epoch is what makes concurrent write-backs into this store discard themselves
		// (ADR 30 rule 3); LayoutStore's only such writer lives in StoreStateLoader, so the
		// counter is asserted here rather than through it.
		vi.mocked( NeoWikiExtension.getInstance ).mockReturnValue( {
			getLayoutRepository: () => ( {
				saveLayout: vi.fn().mockResolvedValue( undefined ),
			} ),
		} as unknown as NeoWikiExtension );
		const store = useLayoutStore();
		const before = store.mutationEpoch;

		await store.saveLayout( newLayout( 'CompanyInfo' ) );

		expect( store.mutationEpoch ).not.toBe( before );
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
