import { describe, expect, it } from 'vitest';
import { defaultSubjectLabel } from '@/domain/defaultSubjectLabel';

describe( 'defaultSubjectLabel', () => {
	it( 'defaults the main subject to the page name', () => {
		expect( defaultSubjectLabel( false, 'Acme Anvil', 'Product' ) ).toBe( 'Acme Anvil' );
	} );

	it( 'defaults a child subject to its schema name', () => {
		expect( defaultSubjectLabel( true, 'Acme Anvil', 'Product' ) ).toBe( 'Product' );
	} );
} );
