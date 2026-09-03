import { describe, expect, it } from 'vitest';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';

describe( 'schemaNameToShow', () => {
	it( 'shows the schema name beside a differently named subject', () => {
		expect( schemaNameToShow( 'Company', 'ACME Inc' ) ).toBe( 'Company' );
	} );

	it( 'shows the schema name when the surface displays no name of its own', () => {
		expect( schemaNameToShow( 'Company', null ) ).toBe( 'Company' );
	} );

	it( 'withholds the schema name when it would only repeat the displayed name', () => {
		expect( schemaNameToShow( 'Appellation', 'Appellation' ) ).toBeNull();
	} );

	it( 'compares exactly, so a differing case is still worth showing', () => {
		expect( schemaNameToShow( 'Appellation', 'appellation' ) ).toBe( 'Appellation' );
	} );
} );
