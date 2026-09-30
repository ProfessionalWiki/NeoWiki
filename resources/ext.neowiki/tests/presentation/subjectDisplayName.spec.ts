import { describe, it, expect, beforeEach } from 'vitest';
import { subjectDisplayName } from '@/presentation/subjectDisplayName';
import { newSubject } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers';

describe( 'subjectDisplayName', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'shows a stored label as it was typed', () => {
		expect( subjectDisplayName( newSubject( { label: 'Rijksmuseum' } ) ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'shows a Subject nobody named under its id, marked as a stand-in', () => {
		expect(
			subjectDisplayName( newSubject( {
				id: 's1demo5sssssss2',
				label: null,
				displayNameIsGenerated: true,
				schemaName: 'Attendance',
			} ) ),
		).toBe( '(s1demo5sssssss2)' );
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
