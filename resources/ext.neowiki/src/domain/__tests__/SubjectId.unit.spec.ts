import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/domain/SubjectId';

describe( 'Subject', () => {

	it( 'can be initialized with valid ID', () => {
		const GUID = 's11111111111111';
		const subjectId = new SubjectId( GUID );

		expect( subjectId.text ).toBe( GUID );
	} );

	// TODO  It seems vitest does not support toThrowError
	// it( 'throws exception when given an invalid GUID', () => {
	// 	const GUID = '7777-0000-000000000001';
	// 	expect( () => new SubjectId( GUID ) ).toThrowError();
	// } );
} );
