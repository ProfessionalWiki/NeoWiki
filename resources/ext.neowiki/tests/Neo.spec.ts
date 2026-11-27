import { describe, expect, it } from 'vitest';
import { Neo } from '@/Neo';

describe( 'Neo registry caching', () => {
	it( 'returns the same PropertyTypeRegistry instance on repeated calls', () => {
		const neo = Neo.getInstance();
		expect( neo.getPropertyTypeRegistry() )
			.toBe( neo.getPropertyTypeRegistry() );
	} );

	it( 'leaves EDTF date to the optional Property Types', () => {
		expect( Neo.getInstance().getPropertyTypeRegistry().hasType( 'edtf' ) ).toBe( false );
	} );
} );
