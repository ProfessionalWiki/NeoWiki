import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { languageOptions, partsForReader, userLanguageTag } from '@/presentation/mediaWikiLanguages';
import { setupMwMock } from '../VueTestHelpers.ts';

describe( 'mediaWikiLanguages', () => {

	describe( 'languageOptions', () => {

		function useLanguages( languageNames: Record<string, string>, config: Record<string, string> = {} ): void {
			setupMwMock( {
				config: { wgUserLanguage: 'en', wgContentLanguage: 'en', ...config },
				languageNames: languageNames,
			} );
		}

		beforeEach( () => {
			useLanguages( {
				'be-tarask': 'беларуская (тарашкевіца)',
				'be-x-old': 'беларуская (старая)',
				EU: 'euskara',
			} );
		} );

		afterEach( () => {
			vi.unstubAllGlobals();
		} );

		function namesByTag(): Record<string, string> {
			return Object.fromEntries( languageOptions().map( ( option ) => [ option.tag, option.name ] ) );
		}

		// MediaWiki maps be-x-old onto be-tarask, so the two codes are one language here.
		it( 'lists each tag once, lowercased, keeping the first name MediaWiki gives for it', () => {
			expect( languageOptions() ).toHaveLength( 2 );
			expect( Object.fromEntries( languageOptions().map( ( option ) => [ option.tag, option.mediaWikiName ] ) ) )
				.toEqual( { 'be-tarask': 'беларуская (тарашкевіца)', eu: 'euskara' } );
		} );

		it( 'names each language in the interface language', () => {
			useLanguages( { de: 'Deutsch', 'pt-br': 'português do Brasil', eu: 'euskara' } );

			expect( namesByTag() ).toEqual( { de: 'German', 'pt-br': 'Brazilian Portuguese', eu: 'Basque' } );
		} );

		// The browser has no names at all in qaa, a code reserved for local use, and names English but
		// not German in Occitan, for which MediaWiki falls back to Catalan.
		it.each( [
			[ 'qaa', [ 'qaa', 'de', 'en' ], { en: 'Englisch', de: 'Deutsch' } ],
			[ 'oc', [ 'oc', 'ca', 'fr', 'en' ], { en: 'anglés', de: 'alemany' } ],
		] )( 'names each language the browser cannot name in %s in the language MediaWiki falls back to', ( userLanguage, fallbackChain, names ) => {
			useLanguages( { en: 'English', de: 'Deutsch' }, { wgUserLanguage: userLanguage } );
			mw.language.getFallbackLanguageChain = vi.fn( () => fallbackChain );

			expect( namesByTag() ).toEqual( names );
		} );

		it.each( [
			[ 'that has no name for it', 'qaa', 'Local language' ],
			[ 'that would name a private-use tag as the language it extends', 'en-x-piglatin', 'Igpay Atinlay' ],
			[ 'that takes its tag for another language', 'tw', 'Twi' ],
		] )( 'names a language as MediaWiki does for a browser %s', ( _, code, mediaWikiName ) => {
			useLanguages( { [ code ]: mediaWikiName } );

			expect( namesByTag() ).toEqual( { [ code ]: mediaWikiName } );
		} );

		it( 'names a language none of the interface languages name as MediaWiki does rather than in the content language', () => {
			useLanguages( { simple: 'Simple English' }, { wgContentLanguage: 'zh' } );

			expect( namesByTag() ).toEqual( { 'en-simple': 'Simple English' } );
		} );

		it( 'names languages as MediaWiki does in a browser without language names', () => {
			useLanguages( { de: 'Deutsch' } );
			vi.stubGlobal( 'Intl', { Locale: Intl.Locale, Collator: Intl.Collator } );

			expect( namesByTag() ).toEqual( { de: 'Deutsch' } );
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
