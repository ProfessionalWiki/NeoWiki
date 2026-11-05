import { describe, it, expect } from 'vitest';
import { blocksSaving, withoutMissingValueViolations, SubjectViolation } from '@/domain/SubjectViolation.ts';
import type { Severity } from '@/domain/Severity.ts';

function violation( code: string, propertyName: string | null = 'Homepage' ): SubjectViolation {
	return { propertyName, code, args: [], severity: 'error', valuePartIndex: null };
}

function found( severity: Severity, introduced?: boolean ): SubjectViolation {
	return {
		...violation( 'max-value' ),
		severity,
		...( introduced === undefined ? {} : { introduced } ),
	};
}

describe( 'blocksSaving', () => {
	const ENFORCED = true;

	it( 'is true for an error the edit introduces, under enforcement', () => {
		expect( blocksSaving( found( 'error', true ), ENFORCED ) ).toBe( true );
	} );

	it( 'is false for a warning the edit introduces', () => {
		expect( blocksSaving( found( 'warning', true ), ENFORCED ) ).toBe( false );
	} );

	it( 'is false for an error the stored Subject already has', () => {
		expect( blocksSaving( found( 'error', false ), ENFORCED ) ).toBe( false );
	} );

	it( 'is false for an error a refused save reported, which does not say whether the edit introduced it', () => {
		expect( blocksSaving( found( 'error' ), ENFORCED ) ).toBe( false );
	} );

	it( 'is false for any violation without enforcement', () => {
		expect( blocksSaving( found( 'error', true ), !ENFORCED ) ).toBe( false );
	} );
} );

describe( 'withoutMissingValueViolations', () => {
	it( 'removes required violations', () => {
		expect( withoutMissingValueViolations( [ violation( 'required' ) ] ) ).toEqual( [] );
	} );

	it( 'keeps subject-level violations, which no longer include a missing label', () => {
		const schemaNotFound = violation( 'schema-not-found', null );

		expect( withoutMissingValueViolations( [ schemaNotFound ] ) ).toEqual( [ schemaNotFound ] );
	} );

	it( 'keeps violations that need a value, dropping missing-value ones in between', () => {
		const invalidUrl = violation( 'invalid-url' );
		const maxLength = violation( 'max-length', 'Title' );

		const result = withoutMissingValueViolations( [
			invalidUrl,
			violation( 'required' ),
			maxLength,
		] );

		expect( result ).toEqual( [ invalidUrl, maxLength ] );
	} );
} );
