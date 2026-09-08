import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createEmptyPage, PageCreationError } from '@/persistence/createEmptyPage.ts';

describe( 'createEmptyPage', () => {
	let createMock: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		createMock = vi.fn().mockResolvedValue( { result: 'Success', pageid: 99 } );
		vi.stubGlobal( 'mw', {
			Api: vi.fn( function ( this: { create: typeof createMock } ) {
				this.create = createMock;
			} ),
		} );
	} );

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it( 'creates the page empty under the given summary and answers with its id', async () => {
		expect( await createEmptyPage( 'New Page', 'why' ) ).toBe( 99 );
		expect( createMock ).toHaveBeenCalledWith( 'New Page', { summary: 'why' }, '' );
	} );

	it( 'reports a taken title as such', async () => {
		createMock.mockRejectedValue( { code: 'articleexists' } );

		await expect( createEmptyPage( 'Taken', '' ) ).rejects.toSatisfy(
			( error: PageCreationError ) => error.titleTaken() && error.title === 'Taken',
		);
	} );

	it( 'reports any other refusal without calling the title taken', async () => {
		createMock.mockRejectedValue( 'invalidtitle' );

		await expect( createEmptyPage( 'Bad|Title', '' ) ).rejects.toSatisfy(
			( error: PageCreationError ) => !error.titleTaken() && error.code === 'invalidtitle',
		);
	} );
} );
