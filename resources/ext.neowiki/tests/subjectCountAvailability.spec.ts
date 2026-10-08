import { describe, expect, it } from 'vitest';
import { setupMwMock } from './VueTestHelpers.ts';
import { areSubjectCountsAvailable } from '@/subjectCountAvailability.ts';

describe( 'areSubjectCountsAvailable', () => {

	it( 'is true when the backend says so', () => {
		setupMwMock( { functions: [ 'config' ], config: { wgNeoWikiSubjectCountsAvailable: true } } );

		expect( areSubjectCountsAvailable() ).toBe( true );
	} );

	it( 'is false when the backend says nothing', () => {
		setupMwMock( { functions: [ 'config' ] } );

		expect( areSubjectCountsAvailable() ).toBe( false );
	} );

} );
