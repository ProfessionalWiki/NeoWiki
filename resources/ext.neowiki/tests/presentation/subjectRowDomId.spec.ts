import { describe, it, expect } from 'vitest';
import { subjectRowDomId, subjectIdFromRowDomId, subjectIdFromHash } from '@/presentation/subjectRowDomId';

const VALID_ID = 's12345abcdefghj';

// The internal row DOM id (rendering + reading the Subject off a dragged row), not a public contract.
describe( 'subjectRowDomId / subjectIdFromRowDomId', () => {

	it( 'round-trips a subject id through its row DOM id', () => {
		expect( subjectIdFromRowDomId( subjectRowDomId( VALID_ID ) ) ).toBe( VALID_ID );
	} );

	it( 'returns null for a string without the row prefix', () => {
		expect( subjectIdFromRowDomId( VALID_ID ) ).toBeNull();
	} );

	it( 'returns null when the prefix is present but the body is not a valid subject id', () => {
		expect( subjectIdFromRowDomId( subjectRowDomId( 'not-a-valid-id' ) ) ).toBeNull();
	} );

	it( 'returns null for a same-length prefix that is not ours', () => {
		// 'ext-neowiki-subject-ROW-' is 24 characters like the real prefix but differs in case, so a
		// naive slice without a prefix check would wrongly extract a valid id from it.
		expect( subjectIdFromRowDomId( 'ext-neowiki-subject-ROW-' + VALID_ID ) ).toBeNull();
	} );

} );

// The public deep-link contract: a Data tab URL fragment is a bare Subject id (like Wikibase's #P123).
describe( 'subjectIdFromHash', () => {

	it( 'accepts a bare subject-id-shaped fragment', () => {
		expect( subjectIdFromHash( VALID_ID ) ).toBe( VALID_ID );
	} );

	it( 'returns null for a fragment that is not a subject id', () => {
		expect( subjectIdFromHash( 'section-heading' ) ).toBeNull();
	} );

	it( 'returns null for an empty fragment', () => {
		expect( subjectIdFromHash( '' ) ).toBeNull();
	} );

	it( 'does not accept the internal row DOM id form as a fragment', () => {
		// The old scheme put the row DOM id in the fragment; the contract is now the bare Subject id, and
		// the long form must not be silently accepted.
		expect( subjectIdFromHash( subjectRowDomId( VALID_ID ) ) ).toBeNull();
	} );

} );
