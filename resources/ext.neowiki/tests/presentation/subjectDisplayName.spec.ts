import { describe, it, expect, beforeEach } from 'vitest';
import { newSubjectNamePreview, subjectDisplayName, unmarkedSubjectName } from '@/presentation/subjectDisplayName';
import { newSubject } from '@/TestHelpers';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { setupMwMock } from '../VueTestHelpers';

beforeEach( () => {
	setupMwMock( {
		messages: {
			'neowiki-subject-generated-name': ( name: string ) => `(unnamed ${ name })`,
		},
	} );
} );

describe( 'subjectDisplayName', () => {

	it( 'shows a stored label as it was typed', () => {
		expect( subjectDisplayName( newSubject( { label: 'Rijksmuseum' } ) ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'marks the Schema name a Subject nobody named falls back to', () => {
		expect(
			subjectDisplayName( newSubject( { label: null, schemaName: 'Attendance' } ) ),
		).toBe( '(unnamed Attendance)' );
	} );

	it( 'leaves a page-name fallback unmarked, since an editor wrote the page title', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				isMainSubject: true,
				pageIdentifiers: new PageIdentifiers( 7, 'Rijksmuseum' ),
			} ) ),
		).toBe( 'Rijksmuseum' );
	} );

	/**
	 * The case a client comparing the display name against the Schema name would get wrong.
	 */
	it( 'leaves a stored label that happens to equal the Schema name unmarked', () => {
		expect(
			subjectDisplayName( newSubject( { label: 'Attendance', schemaName: 'Attendance' } ) ),
		).toBe( 'Attendance' );
	} );

} );

describe( 'unmarkedSubjectName', () => {

	it( 'shows the bare Schema name for a Subject nobody named', () => {
		expect(
			unmarkedSubjectName( newSubject( { label: null, schemaName: 'Attendance' } ) ),
		).toBe( 'Attendance' );
	} );

} );

describe( 'newSubjectNamePreview', () => {

	it( 'marks the Schema name when the page already has a Main Subject', () => {
		expect( newSubjectNamePreview( true, 'Rijksmuseum', 'Attendance' ) ).toBe( '(unnamed Attendance)' );
	} );

	it( 'shows the page name unmarked when the page has no Main Subject yet', () => {
		expect( newSubjectNamePreview( false, 'Rijksmuseum', 'Attendance' ) ).toBe( 'Rijksmuseum' );
	} );

} );
