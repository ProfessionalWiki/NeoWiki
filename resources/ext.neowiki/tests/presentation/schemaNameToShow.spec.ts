import { describe, expect, it } from 'vitest';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';
import { newSubject } from '@/TestHelpers.ts';

describe( 'schemaNameToShow', () => {
	it( 'shows the schema name beside a differently named subject', () => {
		expect( schemaNameToShow( newSubject( { label: 'ACME Inc', schemaName: 'Company' } ) ) ).toBe( 'Company' );
	} );

	it( 'withholds the schema name beside a subject nobody named, which is shown under it already', () => {
		const unnamed = newSubject( { label: null, displayNameIsGenerated: true, schemaName: 'Appellation' } );

		expect( schemaNameToShow( unnamed ) ).toBeNull();
	} );

	it( 'withholds the schema name beside a subject someone labelled after it', () => {
		expect( schemaNameToShow( newSubject( { label: 'Appellation', schemaName: 'Appellation' } ) ) ).toBeNull();
	} );

	it( 'compares exactly, so a differing case is still worth showing', () => {
		expect( schemaNameToShow( newSubject( { label: 'appellation', schemaName: 'Appellation' } ) ) ).toBe( 'Appellation' );
	} );
} );
