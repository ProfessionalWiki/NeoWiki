import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import {
	emittedTags,
	listenForDialogKeys,
	pickInLanguageSearch,
	press,
	pressKey,
	setupMwMock,
} from '../../VueTestHelpers.ts';

// Mounted with the real Codex components: what counts as picking a language is Codex's to decide,
// so stubbed ones would only assert this component's wiring back to itself.
describe( 'LanguagePicker', () => {

	let wrapper: VueWrapper | undefined;

	// A trigger of the kind a caller puts in the picker's slot.
	const TRIGGER = `<template #default="{ expanded, controls, toggle }">
		<button type="button" class="trigger" :aria-expanded="expanded" :aria-controls="controls" @click="toggle">
			Language
		</button>
	</template>`;

	// Attached, because where the focus goes is part of what is asserted.
	function newWrapper( modelValue: string, disabledLanguages: string[] = [] ): VueWrapper {
		wrapper = mount( LanguagePicker, {
			props: { modelValue: modelValue, placement: 'bottom-end', disabledLanguages: disabledLanguages },
			slots: { default: TRIGGER },
			attachTo: document.body,
		} );
		return wrapper;
	}

	function trigger( picker: VueWrapper ): DOMWrapper<HTMLButtonElement> {
		return picker.find( '.trigger' );
	}

	function search( picker: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return picker.find( '.ext-neowiki-language-picker__search input' );
	}

	function panel( picker: VueWrapper ): DOMWrapper<Element> {
		return picker.find( '.ext-neowiki-language-picker__panel' );
	}

	function isOpen( picker: VueWrapper ): boolean {
		return trigger( picker ).attributes( 'aria-expanded' ) === 'true';
	}

	async function openPicker( picker: VueWrapper ): Promise<void> {
		await trigger( picker ).trigger( 'click' );
	}

	async function type( picker: VueWrapper, text: string ): Promise<void> {
		await search( picker ).setValue( text );
	}

	function listedLanguages( picker: VueWrapper ): string[] {
		return picker.findAll( '.cdx-menu-item__text__label' ).map( ( label ) => label.text() );
	}

	function listedTags( picker: VueWrapper ): string[] {
		return picker.findAll( '.cdx-menu-item__text__supporting-text' ).map( ( tag ) => tag.text() );
	}

	function listedDescriptions( picker: VueWrapper ): string[] {
		return picker.findAll( '.cdx-menu-item__text__description' ).map( ( description ) => description.text() );
	}

	// Opens the languages, searches them and picks the first entry the list then offers.
	async function chooseLanguage( picker: VueWrapper, text: string ): Promise<void> {
		await openPicker( picker );
		await pickInLanguageSearch( picker, text );
	}

	function readsIn( userLanguage: string ): void {
		setupMwMock( { config: { wgUserLanguage: userLanguage, wgContentLanguage: 'en' } } );
	}

	beforeEach( () => {
		readsIn( 'en' );
	} );

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
		vi.restoreAllMocks();
	} );

	it( 'keeps its languages closed until its trigger is pressed', () => {
		expect( isOpen( newWrapper( 'eu' ) ) ).toBe( false );
	} );

	it( 'keeps the panel out of sight until its trigger is pressed', async () => {
		const picker = newWrapper( 'eu' );

		expect( panel( picker ).isVisible() ).toBe( false );

		await openPicker( picker );

		expect( panel( picker ).isVisible() ).toBe( true );
	} );

	it( 'opens its languages with the focus in their search field', async () => {
		const picker = newWrapper( 'eu' );

		await openPicker( picker );

		expect( isOpen( picker ) ).toBe( true );
		expect( document.activeElement ).toBe( search( picker ).element );
	} );

	it( 'lists the languages by their name in the interface language', async () => {
		readsIn( 'de' );
		const picker = newWrapper( 'de' );

		await openPicker( picker );

		const listed = listedLanguages( picker );
		expect( listed ).toContain( 'Deutsch' );
		expect( listed ).toContain( 'Englisch' );
		expect( listed ).toEqual( [ ...listed ].sort( ( a, b ) => a.localeCompare( b, 'de' ) ) );
	} );

	it.each( [
		[ 'German', 'German' ],
		[ 'de', 'German' ],
	] )( 'lists first the language %s names or tags, and no tag of its own', async ( typed, first ) => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, typed );

		expect( listedLanguages( picker )[ 0 ] ).toBe( first );
		expect( listedDescriptions( picker ) ).toEqual( [] );
	} );

	it( 'lists the language whose tag is typed before those whose name only starts or contains it', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'es' );

		const listed = listedLanguages( picker );
		expect( listed[ 0 ] ).toBe( 'Spanish' );
		expect( listed.indexOf( 'Estonian' ) ).toBeLessThan( listed.indexOf( 'Portuguese' ) );
	} );

	it( 'offers no tag for typed text naming a listed language', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'ewe' );

		expect( listedLanguages( picker ) ).toEqual( [ 'Ewe' ] );
	} );

	it( 'offers a typed tag alongside the languages whose name it only starts', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'ara' );

		const listed = listedLanguages( picker );
		expect( listed.slice( 0, 2 ) ).toEqual( [ 'Arabic', 'Aragonese' ] );
		expect( listedTags( picker ).slice( 0, 2 ) ).toEqual( [ 'AR', 'AN' ] );
		expect( listed[ listed.length - 1 ] ).toBe( 'ara' );
		expect( listedDescriptions( picker ) ).toEqual( [ 'neowiki-language-picker-tag' ] );
	} );

	it( 'enters a tag it has no name for as the lowercase tag it stands for', async () => {
		const picker = newWrapper( 'en' );

		await chooseLanguage( picker, 'PT-br' );

		expect( emittedTags( picker ) ).toEqual( [ 'pt-br' ] );
	} );

	it( 'offers no tag for a typed word longer than a language subtag', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Klingon' );

		expect( listedLanguages( picker ) ).toEqual( [] );
	} );

	it( 'says so when the search matches no language', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Klingon' );

		expect( picker.find( '.cdx-menu__no-results' ).text() ).toBe( 'neowiki-language-picker-no-results' );
	} );

	it( 'reports the tag of the language chosen by its name, closing and handing the focus back', async () => {
		const picker = newWrapper( 'en' );

		await chooseLanguage( picker, 'Basq' );

		expect( emittedTags( picker ) ).toEqual( [ 'eu' ] );
		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger( picker ).element );
	} );

	it( 'reports nothing for the language it already holds', async () => {
		const picker = newWrapper( 'eu' );

		await chooseLanguage( picker, 'Basq' );

		expect( emittedTags( picker ) ).toEqual( [] );
	} );

	it( 'lists a language it is told is taken as one that cannot be picked', async () => {
		const picker = newWrapper( '', [ 'eu' ] );

		await chooseLanguage( picker, 'Basq' );

		expect( picker.find( '.cdx-menu-item' ).attributes( 'aria-disabled' ) ).toBe( 'true' );
		expect( emittedTags( picker ) ).toEqual( [] );
	} );

	it( 'lists a typed tag it is told is taken as one that cannot be picked', async () => {
		const picker = newWrapper( '', [ 'pt-br' ] );

		await chooseLanguage( picker, 'PT-br' );

		expect( emittedTags( picker ) ).toEqual( [] );
	} );

	it( 'picks the language the arrow keys reach on Enter, handing the focus back to its trigger', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Basq' );
		await pressKey( 'ArrowDown' );
		await pressKey( 'Enter' );

		expect( emittedTags( picker ) ).toEqual( [ 'eu' ] );
		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger( picker ).element );
	} );

	it( 'closes on Escape, handing the focus back to its trigger, without the key reaching the dialog', async () => {
		const picker = newWrapper( 'en' );
		const dialogKeyup = listenForDialogKeys();

		await openPicker( picker );
		await pressKey( 'Escape' );

		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger( picker ).element );
		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'closes on Escape pressed on its trigger while it is open', async () => {
		const picker = newWrapper( 'en' );
		const dialogKeyup = listenForDialogKeys();

		await openPicker( picker );
		trigger( picker ).element.focus();
		await pressKey( 'Escape' );

		expect( isOpen( picker ) ).toBe( false );
		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'lets an Escape pressed on its trigger while it is closed reach the dialog', async () => {
		const picker = newWrapper( 'en' );
		const dialogKeyup = listenForDialogKeys();

		trigger( picker ).element.focus();
		await pressKey( 'Escape' );

		expect( dialogKeyup ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'forgets the entry the arrow keys reached once the search changes', async () => {
		const picker = newWrapper( 'de' );

		await openPicker( picker );
		await pressKey( 'ArrowDown' );
		await type( picker, 'Basq' );
		await pressKey( 'Enter' );
		await pressKey( 'Tab' );

		expect( emittedTags( picker ) ).toEqual( [] );
		expect( search( picker ).attributes( 'aria-activedescendant' ) ).toBeUndefined();
	} );

	it( 'picks nothing on the Enter that ends an IME composition', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Basq' );
		await pressKey( 'ArrowDown' );
		search( picker ).element.dispatchEvent( new KeyboardEvent( 'keyup', { key: 'Enter', isComposing: true, bubbles: true } ) );
		await picker.vm.$nextTick();

		expect( emittedTags( picker ) ).toEqual( [] );
	} );

	it( 'keeps its list out of the tab order', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );

		expect( picker.find( '.cdx-menu__listbox' ).attributes( 'tabindex' ) ).toBe( '-1' );
	} );

	it( 'lists no languages while it is closed', () => {
		expect( listedLanguages( newWrapper( 'en' ) ) ).toEqual( [] );
	} );

	it( 'lets a space be typed into the search field rather than pick', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Basq' );
		await pressKey( 'ArrowDown' );
		const space = new KeyboardEvent( 'keydown', { key: ' ', bubbles: true, cancelable: true } );
		search( picker ).element.dispatchEvent( space );

		expect( space.defaultPrevented ).toBe( false );
		expect( emittedTags( picker ) ).toEqual( [] );
	} );

	it( 'points its search field at the entry the arrow keys reach, for a screen reader', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await pressKey( 'ArrowDown' );
		const highlighted = picker.find( '.cdx-menu-item--highlighted' );

		expect( highlighted.exists() ).toBe( true );
		expect( search( picker ).attributes( 'aria-activedescendant' ) ).toBe( highlighted.attributes( 'id' ) );
	} );

	it( 'keeps trying to put the focus in its search field until the panel is shown', async () => {
		const picker = newWrapper( 'en' );
		// A field in a panel still hidden while useFloatingMenu places it does not take the focus.
		vi.spyOn( HTMLInputElement.prototype, 'focus' ).mockImplementationOnce( () => undefined );

		await openPicker( picker );

		await vi.waitFor( () => expect( document.activeElement ).toBe( search( picker ).element ) );
	} );

	it( 'closes when its trigger is pressed again, keeping the focus on the trigger', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await openPicker( picker );

		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger( picker ).element );
	} );

	it( 'closes when what it sits in scrolls, handing the focus back to its trigger', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		document.body.dispatchEvent( new Event( 'scroll' ) );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger( picker ).element );
	} );

	it( 'stays open when a field outside it scrolls, as a text input does when it is left', async () => {
		const picker = newWrapper( 'en' );
		const elsewhere = document.createElement( 'input' );
		document.body.appendChild( elsewhere );

		await openPicker( picker );
		elsewhere.dispatchEvent( new Event( 'scroll' ) );
		await picker.vm.$nextTick();
		elsewhere.remove();

		expect( isOpen( picker ) ).toBe( true );
		expect( document.activeElement ).toBe( search( picker ).element );
	} );

	it( 'leaves the focus where it is when the page scrolls after it has closed', async () => {
		const picker = newWrapper( 'en' );
		const elsewhere = document.createElement( 'input' );
		document.body.appendChild( elsewhere );

		await openPicker( picker );
		await pressKey( 'Escape' );
		elsewhere.focus();
		document.dispatchEvent( new Event( 'scroll' ) );
		await picker.vm.$nextTick();
		const focused = document.activeElement;
		elsewhere.remove();

		expect( focused ).toBe( elsewhere );
	} );

	it( 'stays open while its own list scrolls', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		picker.find( '.cdx-menu__listbox' ).element.dispatchEvent( new Event( 'scroll' ) );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( true );
	} );

	it( 'closes when the pointer goes down outside it', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		press( document.body );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( false );
	} );

	it( 'listens for an outside press again each time it opens', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await pressKey( 'Escape' );
		await openPicker( picker );
		press( document.body );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( false );
	} );

	it( 'closes when the focus moves on from it', async () => {
		const picker = newWrapper( 'en' );
		const elsewhere = document.createElement( 'input' );
		document.body.appendChild( elsewhere );

		await openPicker( picker );
		await search( picker ).trigger( 'focusout', { relatedTarget: elsewhere } );
		elsewhere.remove();

		expect( isOpen( picker ) ).toBe( false );
	} );

	it( 'stays open while the user is away in another tab or application', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await search( picker ).trigger( 'focusout', { relatedTarget: null } );

		expect( isOpen( picker ) ).toBe( true );
	} );

	it( 'starts a new search each time it opens', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Basq' );
		await pressKey( 'Escape' );
		await openPicker( picker );

		expect( search( picker ).element.value ).toBe( '' );
	} );

	it( 'leaves the focus in the search field when the list is pressed, which is what keeps it open', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );

		expect( press( picker.find( '.cdx-menu' ).element ).defaultPrevented ).toBe( true );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( true );
	} );

	it( 'lets a press on the search field move the focus there', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );

		expect( press( search( picker ).element ).defaultPrevented ).toBe( false );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( true );
	} );

} );
