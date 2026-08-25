import { describe, expect, it } from 'vitest';
import { enteredSubjectLabel } from '@/domain/enteredSubjectLabel.ts';

describe( 'enteredSubjectLabel', () => {
	it( 'reads an empty field as no label', () => {
		expect( enteredSubjectLabel( '' ) ).toBeNull();
	} );

	it( 'reads a field holding only whitespace as no label', () => {
		expect( enteredSubjectLabel( ' \t ' ) ).toBeNull();
	} );

	it( 'trims a label', () => {
		expect( enteredSubjectLabel( '  Acme  ' ) ).toBe( 'Acme' );
	} );
} );
