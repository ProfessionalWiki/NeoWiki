import { describe, it, expect } from 'vitest';
import { withoutMissingValueViolations, SubjectViolation } from '@/domain/SubjectViolation.ts';

function violation( code: string, propertyName: string | null = 'Homepage' ): SubjectViolation {
	return { propertyName, code, args: [], valuePartIndex: null };
}

describe( 'withoutMissingValueViolations', () => {
	it( 'removes required violations', () => {
		expect( withoutMissingValueViolations( [ violation( 'required' ) ] ) ).toEqual( [] );
	} );

	it( 'removes the subject-level label-required violation', () => {
		expect( withoutMissingValueViolations( [ violation( 'label-required', null ) ] ) ).toEqual( [] );
	} );

	it( 'keeps violations that need a value, dropping missing-value ones in between', () => {
		const invalidUrl = violation( 'invalid-url' );
		const maxLength = violation( 'max-length', 'Title' );

		const result = withoutMissingValueViolations( [
			invalidUrl,
			violation( 'required' ),
			violation( 'label-required', null ),
			maxLength,
		] );

		expect( result ).toEqual( [ invalidUrl, maxLength ] );
	} );
} );
