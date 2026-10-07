import { describe, it, expect, vi, beforeEach } from 'vitest';
import { pageDeleteFormUrl } from '@/presentation/subjectDeletion';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { PageSubjects } from '@/domain/PageSubjects';
import type { SubjectRepository } from '@/domain/SubjectRepository';
import { newSubject } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers';

const PAGE_ID = 7;

function page(): PageIdentifiers {
	return new PageIdentifiers( PAGE_ID, 'Anvil' );
}

/** A lookup answering with a page of the given number of Subjects. */
function holding( subjectCount: number ): Pick<SubjectRepository, 'getPageSubjects'> & { getPageSubjects: ReturnType<typeof vi.fn> } {
	const subjects = Array.from(
		{ length: subjectCount },
		( _unused, index ) => newSubject( { id: 's1aaaaaaaaaaaa' + ( index + 1 ) } ),
	);

	return {
		getPageSubjects: vi.fn( () => Promise.resolve( {
			pageSubjects: new PageSubjects( PAGE_ID, null, subjects ),
			referencedSubjects: [],
			schemas: [],
		} ) ),
	} as never;
}

describe( 'pageDeleteFormUrl', () => {

	beforeEach( () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: true }, functions: [ 'config', 'util' ] } );
	} );

	it( 'is the page\'s delete form for a Subject that is its page\'s only one', async () => {
		expect( await pageDeleteFormUrl( page(), holding( 1 ) ) ).toBe( '/wiki/Anvil?action=delete' );
	} );

	it( 'is nothing for a Subject its page holds beside others, which the page outlives', async () => {
		expect( await pageDeleteFormUrl( page(), holding( 2 ) ) ).toBeNull();
	} );

	it( 'is nothing on a page-first wiki, where the page comes first', async () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: false }, functions: [ 'config', 'util' ] } );
		const lookup = holding( 1 );

		expect( await pageDeleteFormUrl( page(), lookup ) ).toBeNull();
		expect( lookup.getPageSubjects ).not.toHaveBeenCalled();
	} );

	it( 'is nothing for a Subject whose page the read did not resolve', async () => {
		const lookup = holding( 1 );

		expect( await pageDeleteFormUrl( null, lookup ) ).toBeNull();
		expect( lookup.getPageSubjects ).not.toHaveBeenCalled();
	} );

	// A blip reading the page leaves the Subject's own delete working, rather than a dead button.
	it( 'is nothing when the page\'s Subjects cannot be read', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const lookup = { getPageSubjects: () => Promise.reject( new Error( 'network down' ) ) } as never;

		expect( await pageDeleteFormUrl( page(), lookup ) ).toBeNull();
	} );

	// Read afresh rather than counted from a listing drawn earlier, which may be missing a Subject
	// somebody else has added since.
	it( 'reads the Subjects of the page the Subject is stored on', async () => {
		const lookup = holding( 1 );

		await pageDeleteFormUrl( page(), lookup );

		expect( lookup.getPageSubjects ).toHaveBeenCalledWith( PAGE_ID );
	} );

} );
