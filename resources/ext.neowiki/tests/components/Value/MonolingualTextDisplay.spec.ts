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
				'neowiki-monolingual-text-display': ( text, language ) => `${ text } (${ language })`,
				'neowiki-monolingual-text-other-languages': ( count ) => `+${ count }`,
				'neowiki-monolingual-text-show-all': ( count ) => `Show all ${ count } languages`,
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
		return wrapper.findAll( '.ext-neowiki-monolingual-text-display__part' ).map( ( line ) => line.text() );
	}

	function toggle( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.cdx-toggle-button' );
	}

	it( 'shows the parts in the interface language, unnamed because the reader is reading it', () => {
		const wrapper = newWrapperFor( KINO, CINEMA, { text: 'Film', language: 'en' } );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema', 'Film' ] );
	} );

	it( 'names the language of a part the interface language falls back to, before the content language', () => {
		readsIn( 'de', 'eu' );

		expect( shownTexts( newWrapperFor( CINE, ZINEMA, CINEMA ) ) ).toEqual( [ 'Cinema (English)' ] );
	} );

	it( 'falls back to the content language once the interface language chain runs out', () => {
		readsIn( 'de', 'eu' );

		expect( shownTexts( newWrapperFor( CINE, ZINEMA ) ) ).toEqual( [ 'Zinema (euskara)' ] );
	} );

	it( 'shows the first part when the reader reads none of the languages', () => {
		readsIn( 'de', 'de' );

		expect( shownTexts( newWrapperFor( CINE, ZINEMA ) ) ).toEqual( [ 'Cine (español)' ] );
	} );

	it( 'marks every part with the language it is in', async () => {
		const wrapper = newWrapperFor( CINEMA, { text: 'فيلم', language: 'ar' } );

		await toggle( wrapper ).trigger( 'click' );

		const parts = wrapper.findAll( '.ext-neowiki-monolingual-text-display__part' );

		expect( parts.map( ( part ) => part.attributes( 'lang' ) ) ).toEqual( [ 'en', 'ar' ] );
		expect( parts.map( ( part ) => part.attributes( 'dir' ) ) ).toEqual( [ 'auto', 'auto' ] );
	} );

	it( 'counts the parts it is not showing on the button that reveals them', () => {
		const wrapper = newWrapperFor( CINEMA, KINO, ZINEMA, CINE );

		expect( toggle( wrapper ).text() ).toBe( '+3' );
		expect( toggle( wrapper ).attributes( 'aria-label' ) ).toBe( 'Show all 4 languages' );
	} );

	it( 'reveals the parts it left out, named, and hides them again', async () => {
		const wrapper = newWrapperFor( CINEMA, KINO, ZINEMA );

		await toggle( wrapper ).trigger( 'click' );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema', 'Kino (Deutsch)', 'Zinema (euskara)' ] );

		await toggle( wrapper ).trigger( 'click' );

		expect( shownTexts( wrapper ) ).toEqual( [ 'Cinema' ] );
	} );

	it( 'offers no button when every part is shown', () => {
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
