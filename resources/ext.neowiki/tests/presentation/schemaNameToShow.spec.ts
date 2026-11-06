import { beforeEach, describe, expect, it } from 'vitest';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';
import { newSubject } from '@/TestHelpers.ts';
import { setupMwMock } from '../VueTestHelpers.ts';

describe( 'schemaNameToShow', () => {
	beforeEach( () => {
		setupMwMock();
	} );

	it( 'shows the schema name beside a differently named subject', () => {
		expect( schemaNameToShow( newSubject( { label: 'ACME Inc', schemaName: 'Company' } ) ) ).toBe( 'Company' );
	} );

	it( 'shows the schema name beside a subject nobody named, which is shown under its id', () => {
		const unnamed = newSubject( { label: null, displayNameIsGenerated: true, schemaName: 'Appellation' } );

		expect( schemaNameToShow( unnamed ) ).toBe( 'Appellation' );
	} );

	it( 'shows the schema name beside a subject named after its page', () => {
		const pageNamed = newSubject( { label: null, displayName: 'Host Page', schemaName: 'Person' } );

		expect( schemaNameToShow( pageNamed ) ).toBe( 'Person' );
	} );

	it( 'withholds the schema name beside a subject someone labelled after it', () => {
		expect( schemaNameToShow( newSubject( { label: 'Appellation', schemaName: 'Appellation' } ) ) ).toBeNull();
	} );

	it( 'compares exactly, so a differing case is still worth showing', () => {
		expect( schemaNameToShow( newSubject( { label: 'appellation', schemaName: 'Appellation' } ) ) ).toBe( 'Appellation' );
	} );

	it( 'withholds the schema name beside a subject labelled after the label the reader sees', () => {
		const firm = newSubject( { label: 'Firm', schemaName: 'Company', schemaLabels: { en: 'Firm' } } );

		expect( schemaNameToShow( firm ) ).toBeNull();
	} );

	it( 'shows the schema name beside a subject labelled after it when the reader sees its label', () => {
		const company = newSubject( { label: 'Company', schemaName: 'Company', schemaLabels: { en: 'Firm' } } );

		expect( schemaNameToShow( company ) ).toBe( 'Company' );
	} );
} );
