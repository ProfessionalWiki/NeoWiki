import { describe, it, expect, vi, beforeEach } from 'vitest';
import { pageDeleteFormUrl } from '@/presentation/subjectDeletion';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { PageSubjects } from '@/domain/PageSubjects';
import type { Subject } from '@/domain/Subject';
import type { SubjectRepository } from '@/domain/SubjectRepository';
import { newSubject } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers';

const PAGE_ID = 7;

const ANVIL = newSubject( { id: 's1aaaaaaaaaaaa1', label: 'Anvil' } );
const HAMMER = newSubject( { id: 's1aaaaaaaaaaaa2', label: 'Hammer' } );
// Named after its Host Subject, as a read names a Dependent Subject.
const ANVIL_FORGING = newSubject( {
	id: 's1aaaaaaaaaaaa3',
	label: null,
	schemaName: 'Forging',
	hostSubject: {
		id: ANVIL.getId().text,
		schemaName: ANVIL.getSchemaName(),
		displayName: 'Anvil',
		displayNameIsGenerated: false,
		hostSubject: null,
	},
} );

function page(): PageIdentifiers {
	return new PageIdentifiers( PAGE_ID, 'Anvil' );
}

/** A lookup answering with a page of these Subjects. */
function holding( ...subjects: Subject[] ): Pick<SubjectRepository, 'getPageSubjects'> & { getPageSubjects: ReturnType<typeof vi.fn> } {
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
		expect( await pageDeleteFormUrl( ANVIL.getId(), page(), holding( ANVIL ) ) ).toBe( '/wiki/Anvil?action=delete' );
	} );

	it( 'is nothing for a Subject its page holds beside others, which the page outlives', async () => {
		expect( await pageDeleteFormUrl( ANVIL.getId(), page(), holding( ANVIL, HAMMER ) ) ).toBeNull();
	} );

	it( 'is the page\'s delete form for a Subject its page holds beside its Dependent Subjects alone', async () => {
		expect( await pageDeleteFormUrl( ANVIL.getId(), page(), holding( ANVIL, ANVIL_FORGING ) ) ).toBe( '/wiki/Anvil?action=delete' );
	} );

	it( 'is nothing for a Dependent Subject, which its page outlives', async () => {
		expect( await pageDeleteFormUrl( ANVIL_FORGING.getId(), page(), holding( ANVIL, ANVIL_FORGING ) ) ).toBeNull();
	} );

	it( 'is nothing on a page-first wiki, where the page comes first', async () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: false }, functions: [ 'config', 'util' ] } );
		const lookup = holding( ANVIL );

		expect( await pageDeleteFormUrl( ANVIL.getId(), page(), lookup ) ).toBeNull();
		expect( lookup.getPageSubjects ).not.toHaveBeenCalled();
	} );

	it( 'is nothing for a Subject whose page the read did not resolve', async () => {
		const lookup = holding( ANVIL );

		expect( await pageDeleteFormUrl( ANVIL.getId(), null, lookup ) ).toBeNull();
		expect( lookup.getPageSubjects ).not.toHaveBeenCalled();
	} );

	// A blip reading the page leaves the Subject's own delete working, rather than a dead button.
	it( 'is nothing when the page\'s Subjects cannot be read', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const lookup = { getPageSubjects: () => Promise.reject( new Error( 'network down' ) ) } as never;

		expect( await pageDeleteFormUrl( ANVIL.getId(), page(), lookup ) ).toBeNull();
	} );

	// Read afresh rather than counted from a listing drawn earlier, which may be missing a Subject
	// somebody else has added since.
	it( 'reads the Subjects of the page the Subject is stored on', async () => {
		const lookup = holding( ANVIL );

		await pageDeleteFormUrl( ANVIL.getId(), page(), lookup );

		expect( lookup.getPageSubjects ).toHaveBeenCalledWith( PAGE_ID );
	} );

} );
