import { describe, it, expect, beforeEach } from 'vitest';
import { subjectRowUrl, dataTabUrl } from '@/presentation/subjectRowUrl';
import { setupMwMock } from '../VueTestHelpers';

describe( 'subjectRowUrl', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'util' ] } );
	} );

	// The Data tab of the page storing it, with the bare Subject id as the fragment: the deep-link
	// anchor that tab resolves to a row.
	it( 'points at the Subject\'s row on the Data tab of the page storing it', () => {
		expect( subjectRowUrl( 'Berlin', 's12345abcdefghj' ) ).toBe( '/wiki/Berlin?action=subjects#s12345abcdefghj' );
	} );

	it( 'builds on the Data tab of that page', () => {
		expect( dataTabUrl( 'Berlin' ) ).toBe( '/wiki/Berlin?action=subjects' );
	} );

} );
