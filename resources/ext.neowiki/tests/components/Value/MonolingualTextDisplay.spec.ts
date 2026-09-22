import { beforeEach, describe, expect, it } from 'vitest';
import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import MonolingualTextDisplay from '@/components/Value/MonolingualTextDisplay.vue';
import { newMonolingualTextValue, newStringValue } from '@/domain/Value';
import { newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers.ts';
import type { MonolingualText, Value } from '@/domain/Value';

describe( 'MonolingualTextDisplay', () => {

	const KINO: MonolingualText = { text: 'Kino', language: 'de' };
	const CINEMA: MonolingualText = { text: 'Cinema', language: 'en' };
	const ZINEMA: MonolingualText = { text: 'Zinema', language: 'eu' };
	const CINE: MonolingualText = { text: 'Cine', language: 'es' };

	// The names are the autonyms MediaWiki lists without CLDR, and the messages render as they do in
	// a wiki, so an assertion reads as what a reader sees.
	function readsIn( userLanguage: string, contentLanguage: string ): void {
		setupMwMock( {
			config: { wgUserLanguage: userLanguage, wgContentLanguage: contentLanguage },
			languageNames: { en: 'English', de: 'Deutsch', eu: 'euskara', es: 'español' },
			messages: {
				'neowiki-monolingual-text-more-languages': ( count ) =>
					Number( count ) === 1 ? '1 more language' : `${ count } more languages`,
				'neowiki-monolingual-text-fewer-languages': 'Fewer languages',
			},
		} );
	}

	beforeEach( () => {
		readsIn( 'en', 'en' );
	} );

	function newWrapper( value: Value ): VueWrapper {
		return createTestWrapper( MonolingualTextDisplay, {
			value: value,
			property: newMonolingualTextProperty( { name: 'Original title', multiple: true } ),
		} );
	}

	function newWrapperFor( ...parts: MonolingualText[] ): VueWrapper {
		return newWrapper( newMonolingualTextValue( parts ) );
	}

	function shownTexts( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-display__part > [lang]' ).map( ( text ) => text.text() );
	}

	/** Each shown part's language as the reader sees it, its tag, or '' when the part carries none. */
	function shownTags( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-display__part' ).map( ( part ) => {
			const tag = part.find( '.ext-neowiki-monolingual-text-display__language > span' );

			return tag.exists() ? tag.text() : '';
		} );
	}

	function toggle( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.ext-neowiki-monolingual-text-display__toggle' );
	}

	it( 'shows the parts in the interface language, untagged because the reader is reading it', () => {
		const wrapper = newWrapperFor( KINO, CINEMA, { text: 'Film', language: 'en' } );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema', 'Film' ] );
		expect( shownTags( wrapper ) ).toEqual( [ '', '' ] );
	} );

	it( 'shows the part the interface language falls back to, before the content language, tagged', () => {
		readsIn( 'de', 'eu' );

		const wrapper = newWrapperFor( CINE, ZINEMA, CINEMA );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema' ] );
		expect( shownTags( wrapper ) ).toEqual( [ 'EN' ] );
	} );

	it( 'falls back to the content language once the interface language chain runs out', () => {
		readsIn( 'de', 'eu' );

		expect( shownTexts( newWrapperFor( CINE, ZINEMA ) ) ).toEqual( [ 'Zinema' ] );
	} );

	it( 'shows the first part when the reader reads none of the languages', () => {
		readsIn( 'de', 'de' );

		expect( shownTexts( newWrapperFor( CINE, ZINEMA ) ) ).toEqual( [ 'Cine' ] );
	} );

	it( 'names the language of a tagged part for anyone who cannot place the tag', () => {
		readsIn( 'de', 'de' );

		const language = newWrapperFor( CINE, ZINEMA ).find( '.ext-neowiki-monolingual-text-display__language' );

		expect( language.attributes( 'title' ) ).toBe( 'español' );
		expect( language.find( '.ext-neowiki-monolingual-text-display__language-name' ).element.textContent )
			.toMatch( /^\s+español$/ );
	} );

	it( 'marks the text of every part with the language it is in, leaving its tag in the reader\'s', async () => {
		const wrapper = newWrapperFor( CINEMA, { text: 'فيلم', language: 'ar' } );

		await toggle( wrapper ).trigger( 'click' );

		const texts = wrapper.findAll( '.ext-neowiki-monolingual-text-display__part > [lang]' );

		expect( texts.map( ( text ) => text.attributes( 'lang' ) ) ).toEqual( [ 'en', 'ar' ] );
		expect( texts.map( ( text ) => text.attributes( 'dir' ) ) ).toEqual( [ 'auto', 'auto' ] );
		const language = wrapper.find( '.ext-neowiki-monolingual-text-display__language' );

		expect( language.attributes( 'lang' ) ).toBeUndefined();
	} );

	it( 'shows the bare tag of a language MediaWiki has no name for, there being no name to read out', () => {
		readsIn( 'de', 'de' );

		const language = newWrapperFor( { text: 'فيلم', language: 'ar' }, CINE )
			.find( '.ext-neowiki-monolingual-text-display__language' );

		expect( language.text() ).toBe( 'AR' );
		expect( language.attributes( 'title' ) ).toBeUndefined();
		expect( language.find( '[aria-hidden]' ).exists() ).toBe( false );
	} );

	it( 'counts the other languages on the link that reveals them', () => {
		const wrapper = newWrapperFor( CINEMA, KINO, ZINEMA, CINE );

		expect( toggle( wrapper ).text() ).toBe( '3 more languages' );
		expect( toggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'counts two texts in one language as one language', () => {
		const wrapper = newWrapperFor( CINEMA, CINE, { text: 'Película', language: 'es' } );

		expect( toggle( wrapper ).text() ).toBe( '1 more language' );
	} );

	it( 'shows every part in the language it falls back to, and counts only the languages still hidden', () => {
		readsIn( 'nl', 'nl' );

		const wrapper = newWrapperFor( { text: 'Bonjour', language: 'fr' }, KINO, { text: 'Salut', language: 'fr' } );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Bonjour', 'Salut' ] );
		expect( toggle( wrapper ).text() ).toBe( '1 more language' );
	} );

	it( 'reveals the other parts, tagged, with the link after them', async () => {
		const wrapper = newWrapperFor( CINEMA, KINO, ZINEMA );

		await toggle( wrapper ).trigger( 'click' );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema', 'Kino', 'Zinema' ] );
		expect( shownTags( wrapper ) ).toEqual( [ '', 'DE', 'EU' ] );
		expect( wrapper.element.lastElementChild ).toBe( toggle( wrapper ).element );
		expect( toggle( wrapper ).text() ).toBe( 'Fewer languages' );
		expect( toggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'hides the other parts again', async () => {
		const wrapper = newWrapperFor( CINEMA, KINO, ZINEMA );

		await toggle( wrapper ).trigger( 'click' );
		await toggle( wrapper ).trigger( 'click' );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema' ] );
	} );

	it( 'offers no link when every part is shown', () => {
		const wrapper = newWrapperFor( CINEMA, { text: 'Film', language: 'en' } );

		expect( toggle( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows nothing for a value with no parts', () => {
		const wrapper = newWrapperFor();

		expect( shownTexts( wrapper ) ).toEqual( [] );
		expect( toggle( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows nothing for a value of another type', () => {
		const wrapper = newWrapper( newStringValue( 'Zinema' ) );

		expect( shownTexts( wrapper ) ).toEqual( [] );
		expect( toggle( wrapper ).exists() ).toBe( false );
	} );

} );
