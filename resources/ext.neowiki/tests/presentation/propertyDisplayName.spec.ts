import { describe, expect, it } from 'vitest';
import { propertyDisplayName } from '@/presentation/propertyDisplayName';
import { newDateProperty } from '@/domain/propertyTypes/Date';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { setupMwMock } from '../VueTestHelpers.ts';

function birthDate( labels?: Record<string, string> ): PropertyDefinition {
	return { ...newDateProperty( { name: 'Birth date' } ), labels: labels };
}

function readingIn( userLanguage: string, contentLanguage: string ): void {
	setupMwMock( { config: { wgUserLanguage: userLanguage, wgContentLanguage: contentLanguage } } );
}

describe( 'propertyDisplayName', () => {

	it( 'is the label in the interface language', () => {
		readingIn( 'de', 'nl' );

		expect( propertyDisplayName( birthDate( { en: 'Date of birth', de: 'Geburtsdatum', nl: 'Geboortedatum' } ) ) )
			.toBe( 'Geburtsdatum' );
	} );

	it( 'falls back along the interface language\'s fallback chain before the content language', () => {
		readingIn( 'de', 'nl' );

		expect( propertyDisplayName( birthDate( { fr: 'Date de naissance', nl: 'Geboortedatum', en: 'Date of birth' } ) ) )
			.toBe( 'Date of birth' );
	} );

	it( 'falls back to the label in the content language', () => {
		readingIn( 'de', 'nl' );

		expect( propertyDisplayName( birthDate( { fr: 'Date de naissance', nl: 'Geboortedatum' } ) ) ).toBe( 'Geboortedatum' );
	} );

	it( 'is the name when no label is in a language the reader reads', () => {
		readingIn( 'de', 'nl' );

		expect( propertyDisplayName( birthDate( { fr: 'Date de naissance' } ) ) ).toBe( 'Birth date' );
	} );

	it( 'is the name of a property without labels', () => {
		readingIn( 'de', 'nl' );

		expect( propertyDisplayName( birthDate() ) ).toBe( 'Birth date' );
	} );

	it( 'falls back as usual for an interface language coded like a property every object has', () => {
		readingIn( 'constructor', 'en' );

		expect( propertyDisplayName( birthDate( { en: 'Date of birth' } ) ) ).toBe( 'Date of birth' );
	} );

	it( 'finds the label in the interface language under its BCP 47 tag', () => {
		readingIn( 'als', 'en' );

		expect( propertyDisplayName( birthDate( { gsw: 'Geburtsdatum', en: 'Date of birth' } ) ) ).toBe( 'Geburtsdatum' );
	} );

} );
