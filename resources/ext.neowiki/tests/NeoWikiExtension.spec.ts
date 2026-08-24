import { describe, expect, it } from 'vitest';
import { defineStore } from 'pinia';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { setupMwMock } from './VueTestHelpers.ts';

describe( 'NeoWikiExtension registry caching', () => {
	it( 'returns the same TypeSpecificComponentRegistry instance on repeated calls', () => {
		const ext = NeoWikiExtension.getInstance();
		expect( ext.getTypeSpecificComponentRegistry() )
			.toBe( ext.getTypeSpecificComponentRegistry() );
	} );

	it( 'returns the same ViewTypeRegistry instance on repeated calls so extension registrations persist', () => {
		const ext = NeoWikiExtension.getInstance();
		expect( ext.getViewTypeRegistry() )
			.toBe( ext.getViewTypeRegistry() );
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

describe( 'NeoWikiExtension.isValidationEnforced', () => {
	it( 'is true only when the wiki says so', () => {
		setupMwMock( { config: { wgNeoWikiEnforceValidation: true } } );
		expect( NeoWikiExtension.getInstance().isValidationEnforced() ).toBe( true );
	} );

	it( 'is false when the wiki does not enforce validation', () => {
		setupMwMock( { config: { wgNeoWikiEnforceValidation: false } } );
		expect( NeoWikiExtension.getInstance().isValidationEnforced() ).toBe( false );
	} );

	it( 'is false when the wiki did not say, since enforcement is opt-in', () => {
		setupMwMock();
		expect( NeoWikiExtension.getInstance().isValidationEnforced() ).toBe( false );
	} );
} );
