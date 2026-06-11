import { describe, it, expect } from 'vitest';
import { parseViolations } from '@/persistence/violationParsing.ts';

describe( 'parseViolations', () => {
	it( 'parses a well-shaped violations body and defaults a missing valuePartIndex to null', () => {
		const result = parseViolations( {
			violations: [ { propertyName: 'Homepage', code: 'invalid-url', args: [] } ],
		} );
		expect( result ).toEqual( [
			{ propertyName: 'Homepage', code: 'invalid-url', args: [], valuePartIndex: null },
		] );
	} );

	it( 'returns null for a malformed body', () => {
		expect( parseViolations( { violations: [ { code: 123 } ] } ) ).toBeNull();
	} );

	it( 'returns null when violations is absent', () => {
		expect( parseViolations( {} ) ).toBeNull();
	} );
} );
