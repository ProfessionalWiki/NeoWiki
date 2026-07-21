import { describe, it, expect } from 'vitest';
import { subjectRowDomId, subjectIdFromRowDomId } from '@/presentation/subjectRowDomId';

const VALID_ID = 's12345abcdefghj';

describe( 'subjectRowDomId', () => {

	// The same literal example is asserted by the PHP counterpart
	// (tests/phpunit/Presentation/SubjectRowAnchorTest.php). Keep them identical so a prefix change on
	// either side breaks a test.
	it( 'prefixes the subject id', () => {
		expect( subjectRowDomId( VALID_ID ) ).toBe( 'ext-neowiki-subject-row-s12345abcdefghj' );
	} );

} );

describe( 'subjectIdFromRowDomId', () => {

	it( 'round-trips a row DOM id back to its subject id', () => {
		expect( subjectIdFromRowDomId( subjectRowDomId( VALID_ID ) ) ).toBe( VALID_ID );
	} );

	it( 'returns null for a bare subject id without the row prefix', () => {
		expect( subjectIdFromRowDomId( VALID_ID ) ).toBeNull();
	} );

	it( 'returns null when the prefix is present but the body is not a valid subject id', () => {
		expect( subjectIdFromRowDomId( subjectRowDomId( 'not-a-valid-id' ) ) ).toBeNull();
	} );

	it( 'returns null for the prefix with an empty body', () => {
		expect( subjectIdFromRowDomId( 'ext-neowiki-subject-row-' ) ).toBeNull();
	} );

	it( 'returns null for an unrelated fragment', () => {
		expect( subjectIdFromRowDomId( 'section-heading' ) ).toBeNull();
	} );

	it( 'returns null for a same-length prefix that is not ours', () => {
		// 'ext-neowiki-subject-ROW-' is 24 characters like the real prefix but differs in case, so a
		// naive slice without a prefix check would wrongly extract a valid id from it.
		expect( subjectIdFromRowDomId( 'ext-neowiki-subject-ROW-' + VALID_ID ) ).toBeNull();
	} );

	it( 'returns null for an empty string', () => {
		expect( subjectIdFromRowDomId( '' ) ).toBeNull();
	} );

} );
