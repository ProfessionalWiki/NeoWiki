import { describe, it, expect, vi, beforeEach } from 'vitest';
import { pageDeleteFormUrl, type PageSubjectCounter } from '@/presentation/subjectDeletion';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { setupMwMock } from '../VueTestHelpers';

const PAGE_ID = 7;

function page(): PageIdentifiers {
	return new PageIdentifiers( PAGE_ID, 'Anvil' );
}

function counting( count: number ): PageSubjectCounter & ReturnType<typeof vi.fn> {
	return vi.fn( () => Promise.resolve( count ) );
}

describe( 'pageDeleteFormUrl', () => {

	beforeEach( () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: true }, functions: [ 'config', 'util' ] } );
	} );

	it( 'is the page\'s delete form for a Subject that is its page\'s only one', async () => {
		expect( await pageDeleteFormUrl( page(), counting( 1 ) ) ).toBe( '/wiki/Anvil?action=delete' );
	} );

	it( 'is nothing for a Subject its page holds beside others, which the page outlives', async () => {
		expect( await pageDeleteFormUrl( page(), counting( 2 ) ) ).toBeNull();
	} );

	it( 'is nothing on a page-first wiki, where the page is the entity', async () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: false }, functions: [ 'config', 'util' ] } );
		const count = counting( 1 );

		expect( await pageDeleteFormUrl( page(), count ) ).toBeNull();
		expect( count ).not.toHaveBeenCalled();
	} );

	it( 'is nothing for a Subject whose page the read did not resolve', async () => {
		const count = counting( 1 );

		expect( await pageDeleteFormUrl( null, count ) ).toBeNull();
		expect( count ).not.toHaveBeenCalled();
	} );

	// A blip reading the page leaves the Subject's own delete working, rather than a dead button.
	it( 'is nothing when the page\'s Subjects cannot be read', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );

		expect( await pageDeleteFormUrl( page(), () => Promise.reject( new Error( 'network down' ) ) ) )
			.toBeNull();
	} );

	it( 'asks for the count of the page the Subject is stored on', async () => {
		const count = counting( 1 );

		await pageDeleteFormUrl( page(), count );

		expect( count ).toHaveBeenCalledWith( PAGE_ID );
	} );

} );
