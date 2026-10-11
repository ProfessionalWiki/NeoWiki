import { beforeEach, describe, expect, it } from 'vitest';
import {
	dataLanguageTags,
	languageName,
	languageOptions,
	newPartLanguageTag,
	partsForReader,
	userLanguageTag,
} from '@/presentation/languages';
import { setupMwMock } from '../VueTestHelpers.ts';

describe( 'languages', () => {

	describe( 'languageName', () => {

		it( 'names a language in the interface language', () => {
			setupMwMock( { config: { wgUserLanguage: 'de' } } );

			expect( languageName( 'en' ) ).toBe( 'Englisch' );
		} );

		it( 'names no language for a tag the browser reads as another language\'s', () => {
			setupMwMock( { config: { wgUserLanguage: 'en' } } );

			expect( languageName( 'sh' ) ).toBeUndefined();
		} );

		it( 'names no language for a tag the browser does not accept', () => {
			setupMwMock( { config: { wgUserLanguage: 'en' } } );

			expect( languageName( 'x-private' ) ).toBeUndefined();
		} );

	} );

	describe( 'languageOptions', () => {

		it( 'lists the ISO 639-1 languages, each under its tag and its name in the interface language', () => {
			setupMwMock( { config: { wgUserLanguage: 'en' } } );

			expect( languageOptions() ).toContainEqual( { tag: 'de', name: 'German' } );
			expect( languageOptions() ).toContainEqual( { tag: 'rm', name: 'Romansh' } );
			expect( languageOptions().map( ( option ) => option.tag ) ).not.toContain( 'gsw' );
		} );

		it( 'lists a name two tags share once, under the first tag', () => {
			setupMwMock( { config: { wgUserLanguage: 'en' } } );

			expect( languageOptions().filter( ( option ) => option.name === 'Akan' ) ).toEqual( [ { tag: 'ak', name: 'Akan' } ] );
		} );

		it( 'lists the languages anew for another interface language', () => {
			setupMwMock( { config: { wgUserLanguage: 'en' } } );
			languageOptions();
			setupMwMock( { config: { wgUserLanguage: 'nl' } } );

			expect( languageOptions() ).toContainEqual( { tag: 'de', name: 'Duits' } );
		} );

	} );

	describe( 'userLanguageTag', () => {

		// MediaWiki's own code for the Simple English wiki is not a BCP 47 tag; setupMwMock's bcp47
		// fake translates it as MediaWiki does.
		it( 'translates the interface language code to its tag', () => {
			setupMwMock( { config: { wgUserLanguage: 'simple' } } );

			expect( userLanguageTag() ).toBe( 'en-simple' );
		} );

	} );

	describe( 'dataLanguageTags', () => {

		it( 'lists the data languages in their configured order, in the form a part stores', () => {
			setupMwMock( { config: { wgContentLanguage: 'en', wgNeoWikiDataLanguages: [ 'fr', 'DE-CH', 'als' ] } } );

			expect( dataLanguageTags() ).toEqual( [ 'fr', 'de-ch', 'gsw' ] );
		} );

		it( 'lists a data language configured under two codes once, where it is first configured', () => {
			setupMwMock( { config: { wgContentLanguage: 'en', wgNeoWikiDataLanguages: [ 'fr', 'als', 'de', 'gsw' ] } } );

			expect( dataLanguageTags() ).toEqual( [ 'fr', 'gsw', 'de' ] );
		} );

		it( 'stands in the content language when no data language is configured', () => {
			setupMwMock( { config: { wgContentLanguage: 'simple', wgNeoWikiDataLanguages: [] } } );

			expect( dataLanguageTags() ).toEqual( [ 'en-simple' ] );
		} );

	} );

	describe( 'newPartLanguageTag', () => {

		beforeEach( () => {
			setupMwMock( { config: { wgContentLanguage: 'en', wgNeoWikiDataLanguages: [ 'de', 'fr', 'it' ] } } );
		} );

		it( 'starts a new part in the first data language no part is in', () => {
			expect( newPartLanguageTag( [ 'de', 'es' ] ) ).toBe( 'fr' );
		} );

		it( 'starts a new part in the content language once every data language has a part', () => {
			expect( newPartLanguageTag( [ 'it', 'de', 'fr' ] ) ).toBe( 'en' );
		} );

	} );

	describe( 'partsForReader', () => {

		const parts = [
			{ text: 'Kino', language: 'de' },
			{ text: 'Zinema', language: 'eu' },
			{ text: 'Zinemaldia', language: 'eu' },
			{ text: 'Cine', language: 'es' },
		];

		it( 'gives every part in the first language the reader reads', () => {
			expect( partsForReader( parts, [ 'fr', 'eu', 'de' ] ) ).toEqual( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Zinemaldia', language: 'eu' },
			] );
		} );

		it( 'gives every part in the first part\'s language when the reader reads none of the languages', () => {
			expect( partsForReader( [ ...parts, { text: 'Film', language: 'de' } ], [ 'fr', 'nl' ] ) ).toEqual( [
				{ text: 'Kino', language: 'de' },
				{ text: 'Film', language: 'de' },
			] );
		} );

		it( 'gives nothing when there are no parts', () => {
			expect( partsForReader( [], [ 'en' ] ) ).toEqual( [] );
		} );

	} );

} );
