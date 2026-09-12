import { describe, it, expect, beforeEach } from 'vitest';
import { newSubjectNamePreview, subjectDisplayName } from '@/presentation/subjectDisplayName';
import { newSubject } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers';

describe( 'subjectDisplayName', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-subject-generated-name': ( name: string ) => `(unnamed ${ name })`,
			},
		} );
	} );

	it( 'shows a stored label as it was typed', () => {
		expect( subjectDisplayName( newSubject( { label: 'Rijksmuseum' } ) ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'marks a name the server derived from the Schema', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				displayName: 'Attendance',
				displayNameIsGenerated: true,
			} ) ),
		).toBe( '(unnamed Attendance)' );
	} );

	it( 'leaves a page-name fallback unmarked, since an editor wrote the page title', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				displayName: 'Rijksmuseum',
				displayNameIsGenerated: false,
			} ) ),
		).toBe( 'Rijksmuseum' );
	} );

	/**
	 * The case a client comparing the display name against the Schema name would get wrong.
	 */
	it( 'leaves a stored label that happens to equal the Schema name unmarked', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: 'Attendance',
				schemaName: 'Attendance',
				displayNameIsGenerated: false,
			} ) ),
		).toBe( 'Attendance' );
	} );

} );

describe( 'newSubjectNamePreview', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-subject-generated-name': ( name: string ) => `(unnamed ${ name })`,
			},
		} );
	} );

	it( 'previews the page name for a main subject on a page that has one', () => {
		expect( newSubjectNamePreview( false, 'Rijksmuseum', [], 'Museum' ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'marks the schema tier for a subject joining a page that has a main subject', () => {
		expect( newSubjectNamePreview( true, 'Rijksmuseum', [], 'Attendance' ) ).toBe( '(unnamed Attendance)' );
	} );

	/**
	 * A page created for the Subject is titled after the Subject id when no label titles it, and an
	 * id is not a name.
	 */
	it( 'marks the schema tier where the page has yet to be titled', () => {
		expect( newSubjectNamePreview( false, null, [], 'Museum' ) ).toBe( '(unnamed Museum)' );
	} );

	it( 'marks the schema tier for a page already titled after a subject it holds', () => {
		expect(
			newSubjectNamePreview( false, 'S11111111111aaa', [ 's11111111111aaa' ], 'Museum' ),
		).toBe( '(unnamed Museum)' );
	} );

} );
