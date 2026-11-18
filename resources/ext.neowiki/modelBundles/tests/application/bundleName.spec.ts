import { describe, expect, it } from 'vitest';
import { isUsableBundleName } from '../../src/application/bundleName';

describe( 'isUsableBundleName', () => {

	it.each( [
		[ 'a plain name', 'Vocabularies' ],
		[ 'an ampersand', 'R&D vocabularies' ],
		[ 'a percent sign', '100% cotton' ],
		[ 'a name its record page title holds in 228 bytes', '数'.repeat( 76 ) ],
	] )( 'accepts %s', ( _case, name ) => {
		expect( isUsableBundleName( name ) ).toBe( true );
	} );

	it.each( [
		[ 'an empty name', ' ' ],
		[ 'a character no page title holds', 'Vocab [EN]' ],
		[ 'a slash, which would title a subpage', 'Vocab/EN' ],
		[ 'a percent-encoded character', 'Vocab%20EN' ],
		[ 'three tildes', 'Vocab ~~~' ],
		[ 'an HTML entity', 'AT&amp;T' ],
		[ 'the replacement character', 'Vocab \ufffd' ],
		[ 'a control character', 'Vocab\u0007' ],
		[ 'a name its record page title cannot hold in 255 bytes', '数'.repeat( 77 ) ],
	] )( 'refuses %s', ( _case, name ) => {
		expect( isUsableBundleName( name ) ).toBe( false );
	} );

} );
