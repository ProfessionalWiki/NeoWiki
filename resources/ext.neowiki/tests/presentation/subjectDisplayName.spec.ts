import { describe, it, expect, beforeEach } from 'vitest';
import { editedSubjectDisplayName, subjectDisplayName } from '@/presentation/subjectDisplayName';
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

describe( 'editedSubjectDisplayName', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-subject-generated-name': ( name: string ) => `(unnamed ${ name })`,
			},
		} );
	} );

	const savedUnderItsTitle = newSubject( { label: null, displayName: 'Madonna', schemaName: 'Artwork' } );

	it( 'shows the label the template reads from the form', () => {
		expect( editedSubjectDisplayName( savedUnderItsTitle, 'Madonna and Child', 'Madonna' ) ).toBe( 'Madonna and Child' );
	} );

	it( 'shows the name the server derived when the template reads nothing from the form or the saved values', () => {
		expect( editedSubjectDisplayName(
			newSubject( { label: null, displayName: 'Rijksmuseum', schemaName: 'Museum' } ),
			null,
			null,
		) ).toBe( 'Rijksmuseum' );
	} );

	it( 'stands the Schema in once the form leaves the template nothing to read', () => {
		expect( editedSubjectDisplayName( savedUnderItsTitle, null, 'Madonna' ) ).toBe( '(unnamed Artwork)' );
	} );

} );
