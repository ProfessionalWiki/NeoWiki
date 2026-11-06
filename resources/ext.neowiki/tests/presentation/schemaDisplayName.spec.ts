import { describe, expect, it } from 'vitest';
import { schemaDisplayName } from '@/presentation/schemaDisplayName';
import { newSchema } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers.ts';
import type { SchemaSummary } from '@/application/SchemaLookup';

function summary( labels: Record<string, string> ): SchemaSummary {
	return { name: 'Company', description: '', propertyCount: 0, subjectsAreDependent: false, labels: labels };
}

function readingIn( userLanguage: string, contentLanguage: string ): void {
	setupMwMock( { config: { wgUserLanguage: userLanguage, wgContentLanguage: contentLanguage } } );
}

describe( 'schemaDisplayName', () => {

	it( 'is the label in the first language the reader reads', () => {
		readingIn( 'de', 'nl' );

		expect( schemaDisplayName( newSchema( { title: 'Company', labels: { nl: 'Bedrijf', de: 'Unternehmen', en: 'Firm' } } ) ) )
			.toBe( 'Unternehmen' );
	} );

	it( 'is the name when no label is in a language the reader reads', () => {
		readingIn( 'de', 'nl' );

		expect( schemaDisplayName( newSchema( { title: 'Company', labels: { fr: 'Entreprise' } } ) ) ).toBe( 'Company' );
	} );

	it( 'is the label of a summarized schema', () => {
		readingIn( 'de', 'nl' );

		expect( schemaDisplayName( summary( { de: 'Unternehmen' } ) ) ).toBe( 'Unternehmen' );
	} );

	it( 'is the name of a summarized schema without a label the reader reads', () => {
		readingIn( 'de', 'nl' );

		expect( schemaDisplayName( summary( { fr: 'Entreprise' } ) ) ).toBe( 'Company' );
	} );

} );
