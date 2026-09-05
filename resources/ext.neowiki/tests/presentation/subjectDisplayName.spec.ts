import { describe, it, expect, beforeEach } from 'vitest';
import { subjectDisplayName } from '@/presentation/subjectDisplayName';
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
