import { describe, expect, it } from 'vitest';
import { SubjectId } from '../SubjectId';

describe( 'Subject', () => {

	it( 'should be initialized with correct Uuid', () => {
		const GUID = '00000000-7777-0000-0000-000000000001';
		const subjectId = new SubjectId( GUID );

		expect( subjectId.text ).toBe( GUID );
	} );

	it( 'given an invalid Uuid', () => {
		const GUID = '7777-0000-000000000001';
		expect( () => new SubjectId( GUID ) ).toThrowError();
	} );
} );
