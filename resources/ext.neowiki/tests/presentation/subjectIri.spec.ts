import { describe, expect, it } from 'vitest';
import { subjectIri } from '@/presentation/subjectIri.ts';
import { setupMwMock } from '../VueTestHelpers.ts';

const IRI_BASE = 'https://data.example.org/entity/';
const LOCAL_ID = 's1aaaaaaaaaaaa1';

function setIriBase( base: string | null ): void {
	setupMwMock( { functions: [ 'config' ], config: { wgNeoWikiSubjectIriBase: base } } );
}

describe( 'subjectIri', () => {

	it( 'names a local Subject under the wiki\'s own base', () => {
		setIriBase( IRI_BASE );

		expect( subjectIri( LOCAL_ID ) ).toBe( IRI_BASE + LOCAL_ID );
	} );

	// A Subject of another Source is named under that Source's base, which this wiki does not hold:
	// minting one here would assert ownership of an entity elsewhere.
	it( 'names no Subject of another Source', () => {
		setIriBase( IRI_BASE );

		expect( subjectIri( 'other:' + LOCAL_ID ) ).toBe( '' );
	} );

	it( 'names nothing that is not a Subject id', () => {
		setIriBase( IRI_BASE );

		expect( subjectIri( 'Not an id' ) ).toBe( '' );
	} );

	// The wiki serves the base as a configuration variable; a page that never received it derives a
	// relative IRI rather than concatenating "null".
	it( 'falls back to the bare id when the wiki served no base', () => {
		setIriBase( null );

		expect( subjectIri( LOCAL_ID ) ).toBe( LOCAL_ID );
	} );

} );
