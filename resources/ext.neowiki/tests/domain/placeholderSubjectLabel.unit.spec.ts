import { describe, expect, it } from 'vitest';
import { placeholderSubjectLabel } from '@/domain/placeholderSubjectLabel';

describe( 'placeholderSubjectLabel', () => {
	it( 'offers the page name for the main subject', () => {
		expect( placeholderSubjectLabel( false, 'Acme Anvil', 'Product' ) ).toBe( 'Acme Anvil' );
	} );

	it( 'offers the schema name for a child subject', () => {
		expect( placeholderSubjectLabel( true, 'Acme Anvil', 'Product' ) ).toBe( 'Product' );
	} );
} );
