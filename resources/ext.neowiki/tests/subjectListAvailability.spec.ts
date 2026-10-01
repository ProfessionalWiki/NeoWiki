import { describe, expect, it } from 'vitest';
import { setupMwMock } from './VueTestHelpers.ts';
import { isSubjectListAvailable } from '@/subjectListAvailability.ts';

describe( 'isSubjectListAvailable', () => {

	it( 'is true when the backend says so', () => {
		setupMwMock( { functions: [ 'config' ], config: { wgNeoWikiSubjectListAvailable: true } } );

		expect( isSubjectListAvailable() ).toBe( true );
	} );

	it( 'is false when the backend says nothing', () => {
		setupMwMock( { functions: [ 'config' ] } );

		expect( isSubjectListAvailable() ).toBe( false );
	} );

} );
