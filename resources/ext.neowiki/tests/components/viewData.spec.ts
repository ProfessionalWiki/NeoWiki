import { describe, expect, it, vi } from 'vitest';
import { getViewsData } from '@/components/viewData';
import { SubjectIdParser } from '@/domain/SubjectIdParser';

const parser = new SubjectIdParser( 'testwiki' );

function viewElements( ...subjectIds: string[] ): NodeListOf<HTMLElement> {
	const container = document.createElement( 'div' );

	for ( const subjectId of subjectIds ) {
		const element = document.createElement( 'div' );
		element.setAttribute( 'data-mw-neowiki-subject-id', subjectId );
		container.appendChild( element );
	}

	return container.querySelectorAll( 'div' );
}

describe( 'getViewsData', () => {

	it( 'uses a bare subject id as the view key', async () => {
		const viewsData = await getViewsData( viewElements( 's11111111111111' ), parser, async () => true );

		expect( viewsData ).toHaveLength( 1 );
		expect( viewsData[ 0 ].id ).toBe( 's11111111111111' );
		expect( viewsData[ 0 ].subjectId.text ).toBe( 's11111111111111' );
	} );

	it( 'canonicalizes an explicitly-local-qualified id to the bare key', async () => {
		const viewsData = await getViewsData( viewElements( 'testwiki:s11111111111111' ), parser, async () => true );

		expect( viewsData ).toHaveLength( 1 );
		expect( viewsData[ 0 ].id ).toBe( 's11111111111111' );
		expect( viewsData[ 0 ].subjectId.text ).toBe( 's11111111111111' );
	} );

	it( 'keeps a foreign-qualified id qualified', async () => {
		const viewsData = await getViewsData( viewElements( 'enwiki:Q42' ), parser, async () => true );

		expect( viewsData ).toHaveLength( 1 );
		expect( viewsData[ 0 ].id ).toBe( 'enwiki:Q42' );
	} );

	it( 'skips elements with an invalid id instead of failing', async () => {
		const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );

		const viewsData = await getViewsData(
			viewElements( 'not-a-subject-id', 's11111111111111' ),
			parser,
			async () => true,
		);

		expect( viewsData ).toHaveLength( 1 );
		expect( viewsData[ 0 ].id ).toBe( 's11111111111111' );
		expect( consoleError ).toHaveBeenCalled();

		consoleError.mockRestore();
	} );

	it( 'skips elements without a subject id', async () => {
		const container = document.createElement( 'div' );
		container.appendChild( document.createElement( 'div' ) );

		const viewsData = await getViewsData( container.querySelectorAll( 'div' ), parser, async () => true );

		expect( viewsData ).toHaveLength( 0 );
	} );

} );
