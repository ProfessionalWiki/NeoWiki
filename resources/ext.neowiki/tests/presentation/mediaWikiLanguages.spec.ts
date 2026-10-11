import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
	dataLanguageTags,
	languageOptions,
	newPartLanguageTag,
	partsForReader,
	userLanguageTag,
} from '@/presentation/mediaWikiLanguages';
import { setupMwMock } from '../VueTestHelpers.ts';

describe( 'mediaWikiLanguages', () => {

	describe( 'languageOptions', () => {

		beforeEach( () => {
			setupMwMock( {
				config: { wgUserLanguage: 'en' },
				languageNames: {
					'be-tarask': 'Belarusian (Taraskievica)',
					'be-x-old': 'Belarusian (old)',
					EU: 'Basque',
				},
			} );
		} );

		// MediaWiki maps be-x-old onto be-tarask, so the two codes are one language here.
		it( 'lists each tag once, lowercased, under the first name given for it', () => {
			expect( languageOptions() ).toEqual( [
				{ tag: 'be-tarask', name: 'Belarusian (Taraskievica)' },
				{ tag: 'eu', name: 'Basque' },
			] );
		} );

		it( 'lists nothing when MediaWiki has no table of language names', () => {
			// Asserted first, so the empty result below is this call's rather than a cache the
			// names above never filled.
			expect( languageOptions() ).not.toEqual( [] );

			mw.language.getData = vi.fn( () => undefined );

			expect( languageOptions() ).toEqual( [] );
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
