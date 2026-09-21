import { describe, it, expect } from 'vitest';
import { subjectLinkUrl, subjectLinkUrlFromRow } from '@/presentation/subjectLinks';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { newSubject } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers';

const SUBJECT_ID = 's1aaaaaaaaaaaa1';

function target(): ReturnType<typeof newSubject> {
	return newSubject( { id: SUBJECT_ID, pageIdentifiers: new PageIdentifiers( 7, 'Anvil' ) } );
}

function onWiki( subjectFirst: boolean ): void {
	setupMwMock( { config: { wgNeoWikiSubjectFirst: subjectFirst }, functions: [ 'config', 'util' ] } );
}

describe( 'subjectLinkUrl', () => {

	it( 'leads to the Subject itself on a subject-first wiki', () => {
		onWiki( true );

		expect( subjectLinkUrl( target() ) ).toBe( '/wiki/Special:Subject/' + SUBJECT_ID );
	} );

	it( 'leads to the page the Subject is stored on on a page-first wiki', () => {
		onWiki( false );

		expect( subjectLinkUrl( target() ) ).toBe( '/wiki/Anvil' );
	} );

} );

describe( 'subjectLinkUrlFromRow', () => {

	it( 'leads to the Subject itself on a subject-first wiki', () => {
		onWiki( true );

		expect( subjectLinkUrlFromRow( target() ) ).toBe( '/wiki/Special:Subject/' + SUBJECT_ID );
	} );

	it( 'leads to the Subject\'s own row on its page\'s Data tab on a page-first wiki', () => {
		onWiki( false );

		expect( subjectLinkUrlFromRow( target() ) ).toBe( '/wiki/Anvil?action=subjects#' + SUBJECT_ID );
	} );

} );
