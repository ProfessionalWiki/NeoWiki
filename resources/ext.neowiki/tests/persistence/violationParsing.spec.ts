import { describe, it, expect } from 'vitest';
import { parseViolations } from '@/persistence/violationParsing.ts';

describe( 'parseViolations', () => {
	it( 'parses a well-shaped violations body and defaults a missing valuePartIndex to null', () => {
		const result = parseViolations( {
			violations: [ { propertyName: 'Homepage', code: 'invalid-url', args: [], severity: 'error' } ],
		} );
		expect( result ).toEqual( [
			{ propertyName: 'Homepage', code: 'invalid-url', args: [], severity: 'error', valuePartIndex: null },
		] );
	} );

	it( 'carries a warning severity through', () => {
		const result = parseViolations( {
			violations: [ { propertyName: 'Score', code: 'max-value', args: [ '100' ], severity: 'warning' } ],
		} );
		expect( result?.[ 0 ].severity ).toBe( 'warning' );
	} );

	it( 'defaults a missing severity to warning', () => {
		const result = parseViolations( {
			violations: [ { propertyName: 'Homepage', code: 'invalid-url', args: [] } ],
		} );
		expect( result?.[ 0 ].severity ).toBe( 'warning' );
	} );

	it( 'returns null for a body carrying an unknown severity', () => {
		expect( parseViolations( {
			violations: [ { propertyName: 'Homepage', code: 'invalid-url', args: [], severity: 'info' } ],
		} ) ).toBeNull();
	} );

	it( 'returns null for a malformed body', () => {
		expect( parseViolations( { violations: [ { code: 123 } ] } ) ).toBeNull();
	} );

	it( 'returns null when violations is absent', () => {
		expect( parseViolations( {} ) ).toBeNull();
	} );
} );
