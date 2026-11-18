import { describe, expect, it } from 'vitest';
import { cyrb53 } from '../../src/application/cyrb53';

describe( 'cyrb53', () => {

	// Install records hold these hashes, so a change to them would show every installed item as changed.
	it.each( [
		[ 'a', '1c2ba782c97901' ],
		[ 'revenge', '0e64cc3b748385' ],
		[ 'revenue', '1d85148d13f93a' ],
	] )( 'hashes "%s" as the reference implementation does', ( text, hash ) => {
		expect( cyrb53( text ) ).toBe( hash );
	} );

} );
