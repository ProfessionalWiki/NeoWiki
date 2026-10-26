import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import LanguageInput from '@/components/common/LanguageInput.vue';
import {
	announcedLanguage,
	chooseFromLanguageList,
	dialogOfAnotherExtension,
	emittedTags,
	guardMenuEscapes,
	listenForDialogKeys,
	openMenuAndPressEscape,
	pickInLanguageSearch,
	setupMwMock,
} from '../../VueTestHelpers.ts';

// Mounted with the real Codex components, and attached, because where the focus goes is part of
// what is asserted.
describe( 'LanguageInput', () => {

	let wrapper: VueWrapper | undefined;

	function wikiWith( dataLanguages: string[] ): void {
		setupMwMock( {
			config: { wgUserLanguage: 'en', wgContentLanguage: 'en', wgNeoWikiDataLanguages: dataLanguages },
			messages: {
				'neowiki-language-input-other': 'Other language…',
			},
		} );
	}

	// Hands each language back as the new one, the way a parent bound to it with v-model does.
	function newWrapper( language: string ): VueWrapper {
		wrapper = mount( LanguageInput, {
			props: {
				modelValue: language,
				label: 'Title language 1',
				'onUpdate:modelValue': ( tag: string ) => wrapper?.setProps( { modelValue: tag } ),
			},
			attachTo: document.body,
		} );
		return wrapper;
	}

	function list( input: VueWrapper ): DOMWrapper<HTMLElement> {
		return input.find( '.ext-neowiki-language-input__handle' );
	}

	// What the eye sees, where a screen reader hears the name.
	function shownTag( input: VueWrapper ): string {
		return list( input ).find( '[aria-hidden="true"]' ).text();
	}

	function listedLanguages( input: VueWrapper ): string[] {
		return input.findAll( '.ext-neowiki-language-input__menu .cdx-menu-item' ).map( ( item ) => item.text() );
	}

	function search( input: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return input.find( '.ext-neowiki-language-picker__search input' );
	}

	function searchIsOpen( input: VueWrapper ): boolean {
		return input.find( '.ext-neowiki-language-picker__panel' ).isVisible();
	}

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
	} );

	it( 'offers the data languages in their configured order, then the other languages', () => {
		wikiWith( [ 'it', 'de', 'fr' ] );

		expect( listedLanguages( newWrapper( 'it' ) ) ).toEqual( [ 'Italian', 'German', 'French', 'Other language…' ] );
	} );

	it( 'offers the language of a text in none of the data languages as one more entry', () => {
		wikiWith( [ 'de', 'fr', 'it' ] );

		expect( listedLanguages( newWrapper( 'es' ) ) )
			.toEqual( [ 'German', 'French', 'Italian', 'Spanish', 'Other language…' ] );
	} );

	it( 'shows the language by its tag', () => {
		wikiWith( [ 'de', 'fr', 'it' ] );

		expect( shownTag( newWrapper( 'fr' ) ) ).toBe( 'FR' );
	} );

	it( 'announces the language the text is in by its name', () => {
		wikiWith( [ 'de', 'fr', 'it' ] );

		expect( announcedLanguage( newWrapper( 'fr' ).element ) ).toBe( 'French' );
	} );

	it( 'announces a language MediaWiki has no name for by its tag', () => {
		wikiWith( [ 'de', 'fr', 'it' ] );

		expect( announcedLanguage( newWrapper( 'und' ).element ) ).toBe( 'und' );
	} );

	it( 'names its list after the field it belongs to', () => {
		wikiWith( [ 'de', 'fr', 'it' ] );

		expect( list( newWrapper( 'fr' ) ).attributes( 'aria-label' ) ).toBe( 'Title language 1' );
	} );

	it( 'shows the language of a text in the wiki\'s one data language, with the others a search away', () => {
		wikiWith( [ 'de' ] );
		const input = newWrapper( 'de' );

		expect( announcedLanguage( input.element ) ).toBe( 'German' );
		expect( listedLanguages( input ) ).toEqual( [ 'German', 'Other language…' ] );
	} );

	it( 'lists the one data language for a text in another, to switch it back to', () => {
		wikiWith( [] );

		expect( listedLanguages( newWrapper( 'de' ) ) ).toEqual( [ 'English', 'German', 'Other language…' ] );
	} );

	it( 'reports the language picked from its list', async () => {
		wikiWith( [ 'de', 'fr', 'it' ] );
		const input = newWrapper( 'fr' );

		await chooseFromLanguageList( input, 'Italian' );

		expect( emittedTags( input ) ).toEqual( [ 'it' ] );
	} );

	it( 'reports nothing for the language it already holds', async () => {
		wikiWith( [ 'de', 'fr', 'it' ] );
		const input = newWrapper( 'fr' );

		await chooseFromLanguageList( input, 'French' );

		expect( emittedTags( input ) ).toEqual( [] );
	} );

	it( 'opens the search over every language from the last entry of its list', async () => {
		wikiWith( [ 'de', 'fr', 'it' ] );
		const input = newWrapper( 'fr' );

		await chooseFromLanguageList( input, 'Other language…' );

		expect( searchIsOpen( input ) ).toBe( true );
		await vi.waitFor( () => expect( document.activeElement ).toBe( search( input ).element ) );
	} );

	it( 'reports the language picked in the search its list opened', async () => {
		wikiWith( [ 'de', 'fr', 'it' ] );
		const input = newWrapper( 'fr' );

		await chooseFromLanguageList( input, 'Other language…' );
		await pickInLanguageSearch( input, 'Basq' );

		expect( emittedTags( input ) ).toEqual( [ 'eu' ] );
	} );

	it( 'hands the focus back to its list once a language is picked in the search', async () => {
		wikiWith( [] );
		const input = newWrapper( 'en' );

		await chooseFromLanguageList( input, 'Other language…' );
		await pickInLanguageSearch( input, 'Germ' );

		expect( document.activeElement ).toBe( list( input ).element );
	} );

	it( 'closes the search when the user turns back to its list', async () => {
		wikiWith( [ 'de', 'fr', 'it' ] );
		const input = newWrapper( 'fr' );

		await chooseFromLanguageList( input, 'Other language…' );
		list( input ).element.focus();
		await input.vm.$nextTick();

		expect( searchIsOpen( input ) ).toBe( false );
	} );

	// MonolingualTextInput, which other extensions can mount, renders it.
	it( 'keeps the Escape that closes its list from a dialog of another extension', async () => {
		wikiWith( [ 'de', 'fr', 'it' ] );
		guardMenuEscapes();
		const dialogKeyup = listenForDialogKeys();
		const input = newWrapper( 'fr' );
		dialogOfAnotherExtension().append( input.element );

		await openMenuAndPressEscape( list( input ).element );

		expect( list( input ).attributes( 'aria-expanded' ) ).toBe( 'false' );
		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

} );
