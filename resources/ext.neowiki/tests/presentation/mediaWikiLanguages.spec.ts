import { beforeEach, describe, expect, it, vi } from 'vitest';
import { languageOptions, partsForReader, userLanguageTag } from '@/presentation/mediaWikiLanguages';
import { setupMwMock } from '../VueTestHelpers.ts';

describe( 'mediaWikiLanguages', () => {

	beforeEach( () => {
		setupMwMock( {
			config: { wgUserLanguage: 'en' },
			languageNames: { be: 'Belarusian', 'be-tarask': 'Belarusian (Taraskievica)', EU: 'Basque' },
		} );
	} );

	describe( 'languageOptions', () => {

		it( 'lowercases each tag', () => {
			expect( languageOptions() ).toContainEqual( { tag: 'eu', name: 'Basque' } );
		} );

		// The real bcp47() maps several MediaWiki codes onto one tag, which is what this is for.
		it( 'lists a tag once, under the first name given for it', () => {
			expect( languageOptions().filter( ( option ) => option.tag === 'be' ) )
				.toEqual( [ { tag: 'be', name: 'Belarusian' } ] );
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

		it( 'reads the interface language as a tag', () => {
			setupMwMock( { config: { wgUserLanguage: 'be-tarask' } } );

			expect( userLanguageTag() ).toBe( 'be' );
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
