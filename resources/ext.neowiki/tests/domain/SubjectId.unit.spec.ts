import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/domain/SubjectId';

describe( 'Subject', () => {

	it( 'can be initialized with valid ID', () => {
		const GUID = 's11111111111111';
		const subjectId = new SubjectId( GUID );

		expect( subjectId.text ).toBe( GUID );
	} );

	it( 'throws when given an invalid ID', () => {
		expect( () => new SubjectId( '7777-0000-000000000001' ) ).toThrowError();
	} );

	it( 'has local source and itself as localId for a bare ID', () => {
		const subjectId = new SubjectId( 's11111111111111' );

		expect( subjectId.source ).toBeNull();
		expect( subjectId.localId ).toBe( 's11111111111111' );
	} );

	it( 'exposes source and localId for a qualified ID', () => {
		const subjectId = new SubjectId( 'enwiki:Q42' );

		expect( subjectId.text ).toBe( 'enwiki:Q42' );
		expect( subjectId.source ).toBe( 'enwiki' );
		expect( subjectId.localId ).toBe( 'Q42' );
	} );

} );
