import { describe, it, expect } from 'vitest';
import { isSubjectFirst } from '@/wikiMode';
import { setupMwMock } from './VueTestHelpers';

describe( 'isSubjectFirst', () => {

	it( 'is true only when the wiki says so', () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: true } } );

		expect( isSubjectFirst() ).toBe( true );
	} );

	it( 'is false on a wiki that says it is page-first', () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: false } } );

		expect( isSubjectFirst() ).toBe( false );
	} );

	it( 'is false when the wiki did not say, since page-first is the default', () => {
		setupMwMock();

		expect( isSubjectFirst() ).toBe( false );
	} );

} );
