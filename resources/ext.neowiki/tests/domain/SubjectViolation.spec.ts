import { describe, it, expect } from 'vitest';
import { withoutRequiredViolations, SubjectViolation } from '@/domain/SubjectViolation.ts';

function violation( code: string, propertyName: string | null = 'Homepage' ): SubjectViolation {
	return { propertyName, code, args: [], valuePartIndex: null };
}

describe( 'withoutRequiredViolations', () => {
	it( 'removes required violations', () => {
		expect( withoutRequiredViolations( [ violation( 'required' ) ] ) ).toEqual( [] );
	} );

	it( 'keeps non-required violations and drops required ones in between', () => {
		const invalidUrl = violation( 'invalid-url' );
		const maxLength = violation( 'max-length', 'Title' );

		const result = withoutRequiredViolations( [ invalidUrl, violation( 'required' ), maxLength ] );

		expect( result ).toEqual( [ invalidUrl, maxLength ] );
	} );
} );
