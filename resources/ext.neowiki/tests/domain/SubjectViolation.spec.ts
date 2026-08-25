import { describe, it, expect } from 'vitest';
import { withoutMissingValueViolations, SubjectViolation } from '@/domain/SubjectViolation.ts';

function violation( code: string, propertyName: string | null = 'Homepage' ): SubjectViolation {
	return { propertyName, code, args: [], severity: 'error', valuePartIndex: null };
}

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
