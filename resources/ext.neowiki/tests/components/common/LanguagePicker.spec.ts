import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxMenu } from '@wikimedia/codex';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import { setupMwMock } from '../../VueTestHelpers.ts';

// Mounted with the real Codex components: what counts as picking a language is Codex's to decide,
// so stubbed ones would only assert this component's wiring back to itself.
describe( 'LanguagePicker', () => {

	let wrapper: VueWrapper | undefined;
	let cleanups: ( () => void )[] = [];

	// Attached, because where the focus goes is part of what is asserted.
	function newWrapper( modelValue: string, label = 'Title language 1' ): VueWrapper {
		wrapper = mount( LanguagePicker, {
			props: { modelValue: modelValue, label: label },
			attachTo: document.body,
		} );
		return wrapper;
	}

	function button( picker: VueWrapper ): DOMWrapper<HTMLButtonElement> {
		return picker.find( '.ext-neowiki-language-picker__button' );
	}

	function search( picker: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return picker.find( '.ext-neowiki-language-picker__search input' );
	}

	function panel( picker: VueWrapper ): DOMWrapper<Element> {
		return picker.find( '.ext-neowiki-language-picker__panel' );
	}

	// What the control's aria-controls names, resolved the way a screen reader resolves it.
	function controlledElement( control: DOMWrapper<Element> ): Element | null {
		return document.getElementById( control.attributes( 'aria-controls' ) ?? '' );
	}

	function isOpen( picker: VueWrapper ): boolean {
		return button( picker ).attributes( 'aria-expanded' ) === 'true';
	}

	async function openPicker( picker: VueWrapper ): Promise<void> {
		await button( picker ).trigger( 'click' );
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

	// More than one page of them, so the list both scrolls and has a page left to load.
	function manyLanguages(): Record<string, string> {
		return Object.fromEntries( Array.from( { length: 60 }, ( _, i ) => [ `l${ i }`, `Language ${ i }` ] ) );
	}

	// Opens the languages, searches them and picks the first entry the list then offers.
	async function chooseLanguage( picker: VueWrapper, text: string ): Promise<void> {
		await openPicker( picker );
		await type( picker, text );
		await picker.findAll( '.cdx-menu-item:not( .cdx-menu__no-results )' )[ 0 ].trigger( 'click' );
	}

	function emittedTags( picker: VueWrapper ): string[] {
		return ( picker.emitted( 'update:modelValue' ) ?? [] ).map( ( event ) => event[ 0 ] as string );
	}

	// Dispatched rather than trigger()'d, because what the test asserts on is the event itself.
	function press( element: Element ): MouseEvent {
		const event = new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } );

		element.dispatchEvent( event );

		return event;
	}

	// Keys go where the focus is, which is what the handlers move: each press follows it.
	async function pressKey( key: string ): Promise<void> {
		for ( const type of [ 'keydown', 'keyup' ] ) {
			document.activeElement?.dispatchEvent( new KeyboardEvent( type, { key: key, bubbles: true, cancelable: true } ) );
			await wrapper?.vm.$nextTick();
		}
	}

	function listenForDialogKeys(): ReturnType<typeof vi.fn> {
		const dialogKeyup = vi.fn();
		document.addEventListener( 'keyup', dialogKeyup );
		cleanups.push( () => document.removeEventListener( 'keyup', dialogKeyup ) );
		return dialogKeyup;
	}

	function useLanguages( languageNames: Record<string, string>, config: Record<string, string> = {} ): void {
		setupMwMock( {
			config: { wgUserLanguage: 'en', wgContentLanguage: 'en', ...config },
			languageNames: languageNames,
		} );
	}

	beforeEach( () => {
		useLanguages( { en: 'English', eu: 'Basque', es: 'Spanish', an: 'Aragonese' } );
	} );

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
		cleanups.forEach( ( cleanup ) => cleanup() );
		cleanups = [];
		vi.restoreAllMocks();
	} );

	it( 'shows the tag of the language it holds on its button', () => {
		expect( button( newWrapper( 'eu' ) ).text() ).toBe( 'EU' );
	} );

	it( 'names the language on its button for anyone who cannot place the tag', () => {
		expect( button( newWrapper( 'eu' ) ).attributes( 'aria-label' ) ).toContain( 'Basque' );
	} );

	it( 'names on its button the row it belongs to, which is the only tab stop the row has', () => {
		expect( button( newWrapper( 'eu', 'Title language 2' ) ).attributes( 'aria-label' ) )
			.toContain( 'Title language 2' );
	} );

	it( 'names a language MediaWiki has no name for by its tag', () => {
		setupMwMock( {
			config: { wgUserLanguage: 'en', wgContentLanguage: 'en' },
			languageNames: { en: 'English' },
			messages: {
				'neowiki-language-picker-button': ( label, name, tag ) => `${ label }: ${ name } (${ tag })`,
			},
		} );

		expect( button( newWrapper( 'und', 'Title language 1' ) ).attributes( 'aria-label' ) )
			.toBe( 'Title language 1: und (und)' );
	} );

	it( 'keeps its languages closed until the button is pressed', () => {
		expect( isOpen( newWrapper( 'eu' ) ) ).toBe( false );
	} );

	it( 'keeps the panel out of sight until the button is pressed', async () => {
		const picker = newWrapper( 'eu' );

		expect( panel( picker ).isVisible() ).toBe( false );

		await openPicker( picker );

		expect( panel( picker ).isVisible() ).toBe( true );
	} );

	it( 'names the panel its button opens, and the list its search field drives', async () => {
		const picker = newWrapper( 'eu' );

		await openPicker( picker );

		expect( controlledElement( button( picker ) ) ).toBe( panel( picker ).element );
		expect( controlledElement( search( picker ) ) ).toBe( picker.find( '.cdx-menu__listbox' ).element );
	} );

	it( 'opens its languages with the focus in their search field', async () => {
		const picker = newWrapper( 'eu' );

		await openPicker( picker );

		expect( isOpen( picker ) ).toBe( true );
		expect( document.activeElement ).toBe( search( picker ).element );
	} );

	it( 'lists the languages the reader reads first, then the others by name', async () => {
		useLanguages(
			{ an: 'Aragonese', cy: 'Welsh', de: 'German', en: 'English', es: 'Spanish', eu: 'Basque' },
			{ wgUserLanguage: 'es', wgContentLanguage: 'eu' },
		);
		const picker = newWrapper( 'es' );

		await openPicker( picker );

		expect( listedLanguages( picker ) )
			.toEqual( [ 'Spanish', 'English', 'Basque', 'Aragonese', 'German', 'Welsh' ] );
	} );

	it.each( [
		[ 'Ido', 'Ido' ],
		[ 'eu', 'Basque' ],
	] )( 'lists the language %s names, and no tag of its own', async ( typed, listed ) => {
		useLanguages( { en: 'English', eu: 'Basque', io: 'Ido' } );
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, typed );

		expect( listedLanguages( picker ) ).toEqual( [ listed ] );
	} );

	it( 'marks the language it holds, so the reader sees which of them it is', async () => {
		const picker = newWrapper( 'eu' );

		await openPicker( picker );

		expect( picker.find( '.cdx-menu-item--selected .cdx-menu-item__text__label' ).text() ).toBe( 'Basque' );
	} );

	it( 'lists the language whose tag is typed before those whose name only starts or contains it', async () => {
		useLanguages( { an: 'Aragonese', es: 'Spanish', et: 'Estonian' } );
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'es' );

		expect( listedLanguages( picker ) ).toEqual( [ 'Spanish', 'Estonian', 'Aragonese' ] );
	} );

	it( 'lists the language a MediaWiki code stands for', async () => {
		useLanguages( { als: 'Alemannisch', en: 'English' } );
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'als' );

		expect( listedLanguages( picker ) ).toEqual( [ 'Alemannisch' ] );
	} );

	it( 'offers a typed tag alongside the languages whose name it only starts', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'ara' );

		expect( listedLanguages( picker ) ).toEqual( [ 'Aragonese', 'ara' ] );
		expect( listedTags( picker ) ).toEqual( [ 'AN' ] );
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
		await type( picker, 'German' );

		expect( listedLanguages( picker ) ).toEqual( [] );
	} );

	it( 'says so when the search matches no language', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'German' );

		expect( picker.find( '.cdx-menu__no-results' ).text() ).toBe( 'neowiki-language-picker-no-results' );
	} );

	it( 'lists more languages once the list is scrolled to its end', async () => {
		useLanguages( manyLanguages() );
		const picker = newWrapper( 'en' );

		await openPicker( picker );

		expect( listedLanguages( picker ) ).toHaveLength( 50 );

		await picker.findComponent( CdxMenu ).vm.$emit( 'load-more' );

		expect( listedLanguages( picker ) ).toHaveLength( 60 );
	} );

	it( 'lists one page again once the search changes', async () => {
		useLanguages( manyLanguages() );
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await picker.findComponent( CdxMenu ).vm.$emit( 'load-more' );
		await type( picker, 'Language' );

		expect( listedLanguages( picker ) ).toHaveLength( 50 );
	} );

	it( 'lists one page again each time it opens', async () => {
		useLanguages( manyLanguages() );
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await picker.findComponent( CdxMenu ).vm.$emit( 'load-more' );
		await pressKey( 'Escape' );
		await openPicker( picker );

		expect( listedLanguages( picker ) ).toHaveLength( 50 );
	} );

	it( 'reports the tag of the language chosen by its name, closing and handing the focus back', async () => {
		const picker = newWrapper( 'en' );

		await chooseLanguage( picker, 'Basq' );

		expect( emittedTags( picker ) ).toEqual( [ 'eu' ] );
		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( button( picker ).element );
	} );

	it( 'reports nothing for the language it already holds', async () => {
		const picker = newWrapper( 'eu' );

		await chooseLanguage( picker, 'Basq' );

		expect( emittedTags( picker ) ).toEqual( [] );
	} );

	it( 'picks the language the arrow keys reach on Enter, handing the focus back to its button', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await type( picker, 'Basq' );
		await pressKey( 'ArrowDown' );
		await pressKey( 'Enter' );

		expect( emittedTags( picker ) ).toEqual( [ 'eu' ] );
		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( button( picker ).element );
	} );

	it( 'closes on Escape, handing the focus back to its button, without the key reaching the dialog', async () => {
		const picker = newWrapper( 'en' );
		const dialogKeyup = listenForDialogKeys();

		await openPicker( picker );
		await pressKey( 'Escape' );

		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( button( picker ).element );
		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'closes on Escape pressed on its button while it is open', async () => {
		const picker = newWrapper( 'en' );
		const dialogKeyup = listenForDialogKeys();

		await openPicker( picker );
		button( picker ).element.focus();
		await pressKey( 'Escape' );

		expect( isOpen( picker ) ).toBe( false );
		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'lets an Escape pressed on its button while it is closed reach the dialog', async () => {
		const picker = newWrapper( 'en' );
		const dialogKeyup = listenForDialogKeys();

		button( picker ).element.focus();
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

	it( 'stays open on the Escape that cancels an IME composition', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		search( picker ).element.dispatchEvent(
			new KeyboardEvent( 'keyup', { key: 'Escape', isComposing: true, bubbles: true } ),
		);
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( true );
	} );

	// Enough of the list is on show to browse, and the panel never outgrows the dialog it opens in.
	it( 'caps how many entries its list shows at once', () => {
		expect( newWrapper( 'en' ).findComponent( CdxMenu ).props( 'visibleItemLimit' ) ).toBe( 8 );
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

	it( 'points its search field at an entry that is still listed once more languages are', async () => {
		useLanguages( manyLanguages() );
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await pressKey( 'ArrowDown' );
		await picker.findComponent( CdxMenu ).vm.$emit( 'load-more' );
		await picker.vm.$nextTick();
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

	it( 'closes when its button is pressed again, keeping the focus on the button', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		await openPicker( picker );

		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( button( picker ).element );
	} );

	it( 'leaves the focus where it is when its button is pressed', () => {
		expect( press( button( newWrapper( 'en' ) ).element ).defaultPrevented ).toBe( true );
	} );

	it( 'closes when what it sits in scrolls, handing the focus back to its button', async () => {
		const picker = newWrapper( 'en' );

		await openPicker( picker );
		document.body.dispatchEvent( new Event( 'scroll' ) );
		await picker.vm.$nextTick();

		expect( isOpen( picker ) ).toBe( false );
		expect( document.activeElement ).toBe( button( picker ).element );
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
