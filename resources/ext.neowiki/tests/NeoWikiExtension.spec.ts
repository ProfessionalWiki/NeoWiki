import { describe, expect, it } from 'vitest';
import { NeoWikiExtension } from '@/NeoWikiExtension';

describe( 'NeoWikiExtension registry caching', () => {
	it( 'returns the same TypeSpecificComponentRegistry instance on repeated calls', () => {
		const ext = NeoWikiExtension.getInstance();
		expect( ext.getTypeSpecificComponentRegistry() )
			.toBe( ext.getTypeSpecificComponentRegistry() );
	} );
} );
