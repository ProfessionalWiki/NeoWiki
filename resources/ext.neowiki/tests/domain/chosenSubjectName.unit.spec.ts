import { describe, expect, it } from 'vitest';
import { chosenSubjectName } from '@/domain/chosenSubjectName';

describe( 'chosenSubjectName', () => {

	it( 'is the stored label when the Subject has one', () => {
		expect( chosenSubjectName( 'Rijksmuseum', false, 'Museums of Amsterdam' ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'is the page name for a Main Subject with no label', () => {
		expect( chosenSubjectName( null, true, 'Rijksmuseum' ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'is nothing for a Child Subject with no label', () => {
		expect( chosenSubjectName( null, false, 'Rijksmuseum' ) ).toBeNull();
	} );

	it( 'is nothing for a Main Subject with no label whose page has no name', () => {
		expect( chosenSubjectName( null, true, '' ) ).toBeNull();
	} );

} );
