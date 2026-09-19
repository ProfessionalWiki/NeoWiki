import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import { setupMwMock } from '../../VueTestHelpers.ts';

// Mounted with the real Codex component: what counts as picking a language is Codex's to decide,
// so a stubbed field would only assert this component's wiring back to itself.
describe( 'LanguagePicker', () => {

	function newWrapper( modelValue: string ): VueWrapper {
		return mount( LanguagePicker, { props: { modelValue: modelValue } } );
	}

	function field( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return wrapper.find( 'input' );
	}

	function listedLanguages( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-menu-item__text__label' ).map( ( label ) => label.text() );
	}

	function listedDescriptions( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-menu-item__text__description' ).map( ( description ) => description.text() );
	}

	async function type( wrapper: VueWrapper, text: string ): Promise<void> {
		await field( wrapper ).setValue( text );
	}

	async function pickFirstEntry( wrapper: VueWrapper ): Promise<void> {
		await wrapper.findAll( '.cdx-menu-item:not( .cdx-menu__no-results )' )[ 0 ].trigger( 'click' );
	}

	function emittedTags( wrapper: VueWrapper ): string[] {
		return ( wrapper.emitted( 'update:modelValue' ) ?? [] ).map( ( event ) => event[ 0 ] as string );
	}

	async function newWrapperShowingItsMenu(): Promise<VueWrapper> {
		const wrapper = newWrapper( 'en' );

		await field( wrapper ).trigger( 'focus' );
		await type( wrapper, 'Basq' );

		return wrapper;
	}

	// Triggered rather than wrapper.trigger()'d, because what the test asserts on is the event itself.
	function press( element: Element ): MouseEvent {
		const event = new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } );

		element.dispatchEvent( event );

		return event;
	}

	beforeEach( () => {
		setupMwMock( {
			config: { wgUserLanguage: 'en' },
			languageNames: { en: 'English', eu: 'Basque', es: 'Spanish', an: 'Aragonese' },
		} );
	} );

	it( 'shows the name of a language it knows rather than its tag', () => {
		expect( field( newWrapper( 'eu' ) ).element.value ).toBe( 'Basque' );
	} );

	it( 'shows the tag of a language it has no name for', () => {
		expect( field( newWrapper( 'und' ) ).element.value ).toBe( 'und' );
	} );

	it( 'lists the language the typed text names, and no tag of its own', async () => {
		const wrapper = newWrapper( 'en' );

		await type( wrapper, 'Basque' );

		expect( listedLanguages( wrapper ) ).toEqual( [ 'Basque' ] );
	} );

	it( 'lists the language a typed tag names, and no tag of its own', async () => {
		const wrapper = newWrapper( 'en' );

		await type( wrapper, 'eu' );

		expect( listedLanguages( wrapper ) ).toEqual( [ 'Basque' ] );
	} );

	it( 'offers a typed tag alongside the languages whose name it only starts', async () => {
		const wrapper = newWrapper( 'en' );

		await type( wrapper, 'ara' );

		expect( listedLanguages( wrapper ) ).toEqual( [ 'Aragonese', 'ara' ] );
		expect( listedDescriptions( wrapper ) ).toEqual( [ 'an', 'neowiki-language-picker-tag' ] );
	} );

	it( 'offers a well-formed tag it has no name for, so it can be chosen too', async () => {
		const wrapper = newWrapper( 'en' );

		await type( wrapper, 'und' );
		await pickFirstEntry( wrapper );

		expect( emittedTags( wrapper ) ).toEqual( [ 'und' ] );
	} );

	it( 'offers nothing for text that is no language at all', async () => {
		const wrapper = newWrapper( 'eu' );

		await type( wrapper, 'not a language' );

		expect( listedLanguages( wrapper ) ).toEqual( [] );
	} );

	it( 'reports the tag of the language chosen by its name', async () => {
		const wrapper = newWrapper( 'en' );

		await type( wrapper, 'Basq' );
		await pickFirstEntry( wrapper );

		expect( emittedTags( wrapper ) ).toEqual( [ 'eu' ] );
	} );

	it( 'reports nothing for the language it already holds', async () => {
		const wrapper = newWrapper( 'eu' );

		await type( wrapper, 'Basq' );
		await pickFirstEntry( wrapper );

		expect( emittedTags( wrapper ) ).toEqual( [] );
	} );

	it( 'leaves the focus where it is when the menu is pressed, which is what keeps it open', async () => {
		const wrapper = await newWrapperShowingItsMenu();

		expect( press( wrapper.find( '.cdx-menu' ).element ).defaultPrevented ).toBe( true );
	} );

	it( 'lets a press on the field move the focus there', async () => {
		const wrapper = await newWrapperShowingItsMenu();

		expect( press( field( wrapper ).element ).defaultPrevented ).toBe( false );
	} );

	it( 'keeps the language it had when what was typed was never chosen', async () => {
		const wrapper = newWrapper( 'eu' );

		await type( wrapper, 'Span' );
		await field( wrapper ).trigger( 'blur' );

		expect( emittedTags( wrapper ) ).toEqual( [] );
		expect( field( wrapper ).element.value ).toBe( 'Basque' );
	} );

} );
