import { describe, expect, it } from 'vitest';
import { defineStore } from 'pinia';
import { NeoWikiExtension } from '@/NeoWikiExtension';

describe( 'NeoWikiExtension registry caching', () => {
	it( 'returns the same TypeSpecificComponentRegistry instance on repeated calls', () => {
		const ext = NeoWikiExtension.getInstance();
		expect( ext.getTypeSpecificComponentRegistry() )
			.toBe( ext.getTypeSpecificComponentRegistry() );
	} );
} );

describe( 'NeoWikiExtension.getPinia', () => {
	it( 'returns the same Pinia instance on every call', () => {
		const ext = NeoWikiExtension.getInstance();
		const a = ext.getPinia();
		const b = ext.getPinia();
		expect( a ).toBe( b );
	} );

	it( 'state mutations through one store consumer are visible to another using the same Pinia', () => {
		const pinia = NeoWikiExtension.getInstance().getPinia();
		const useTestStore = defineStore( 'test-shared-state', {
			state: () => ( { count: 0 } ),
		} );

		useTestStore( pinia ).count = 7;

		expect( useTestStore( pinia ).count ).toBe( 7 );
	} );
} );
