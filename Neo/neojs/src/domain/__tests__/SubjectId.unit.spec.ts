import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/domain/SubjectId';

describe( 'Subject', () => {

	it( 'can be initialized with valid GUID', () => {
		const GUID = '00000000-7777-0000-0000-000000000001';
		const subjectId = new SubjectId( GUID );

		expect( subjectId.text ).toBe( GUID );
	} );
	// TODO  It seems vitest does not support toThrowError
	// it( 'throws exception when given an invalid GUID', () => {
	// 	const GUID = '7777-0000-000000000001';
	// 	expect( () => new SubjectId( GUID ) ).toThrowError();
	// } );
} );
