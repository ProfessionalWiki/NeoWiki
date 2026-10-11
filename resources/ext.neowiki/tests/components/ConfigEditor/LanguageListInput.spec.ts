import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import LanguageListInput from '@/components/ConfigEditor/LanguageListInput.vue';
import { listedLanguages, setupMwMock } from '../../VueTestHelpers.ts';

// Mounted with the real language search and Codex components, and attached, because where the focus
// goes is part of what is asserted.
describe( 'LanguageListInput', () => {

	let wrapper: VueWrapper | undefined;

	function wikiIn( userLanguage: string, contentLanguage: string ): void {
		setupMwMock( {
			config: { wgUserLanguage: userLanguage, wgContentLanguage: contentLanguage },
			messages: {
				'neowiki-config-editor-add-language': 'Add language',
				'neowiki-config-editor-move-language-up': ( name: string ) => `Move ${ name } up`,
				'neowiki-config-editor-move-language-down': ( name: string ) => `Move ${ name } down`,
				'neowiki-config-editor-remove-language': ( name: string ) => `Remove ${ name }`,
				'neowiki-config-editor-language-added': ( name: string ) => `${ name } added.`,
				'neowiki-config-editor-language-moved': ( name: string, position: string, count: string ) =>
					`${ name } moved to position ${ position } of ${ count }.`,
				'neowiki-config-editor-language-removed': ( name: string ) => `${ name } removed.`,
			},
		} );
	}

	beforeEach( () => {
		wikiIn( 'en', 'en' );
	} );

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
	} );

	// Hands each list back as the new one, the way a parent bound to it with v-model does.
	function newWrapper( tags: string[], disabled = false ): VueWrapper {
		wrapper = mount( LanguageListInput, {
			props: {
				modelValue: tags,
				label: 'Data languages',
				disabled: disabled,
				'onUpdate:modelValue': ( newTags: string[] ) => wrapper?.setProps( { modelValue: newTags } ),
			},
			attachTo: document.body,
		} );
		return wrapper;
	}

	function button( list: VueWrapper, label: string ): DOMWrapper<HTMLButtonElement> {
		return list.find( `button[aria-label="${ label }"]` );
	}

	async function press( list: VueWrapper, label: string ): Promise<void> {
		await button( list, label ).trigger( 'click' );
		await list.vm.$nextTick();
	}

	function addButton( list: VueWrapper ): DOMWrapper<HTMLButtonElement> {
		const button = list.findAll<HTMLButtonElement>( 'button' ).find( ( candidate ) => candidate.text() === 'Add language' );

		expect( button ).toBeDefined();
		return button!;
	}

	async function searchFor( list: VueWrapper, text: string ): Promise<void> {
		await addButton( list ).trigger( 'click' );
		await list.find( '.ext-neowiki-language-picker__search input' ).setValue( text );
	}

	function offeredLanguage( list: VueWrapper, name: string ): DOMWrapper<Element> {
		return list.findAll( '.ext-neowiki-language-picker__menu .cdx-menu-item' )
			.find( ( item ) => item.find( '.cdx-menu-item__text__label' ).text() === name )!;
	}

	function announcement( list: VueWrapper ): string {
		return list.find( '[aria-live="polite"], [role="status"]' ).text();
	}

	it( 'lists its languages in their order, by name', () => {
		expect( listedLanguages( newWrapper( [ 'fr', 'de' ] ) ) ).toEqual( [ 'French', 'German' ] );
	} );

	it( 'lists a language MediaWiki has no name for by its tag', () => {
		expect( listedLanguages( newWrapper( [ 'und' ] ) ) ).toEqual( [ 'und' ] );
	} );

	it( 'lists a language the list holds twice once', () => {
		expect( listedLanguages( newWrapper( [ 'de', 'fr', 'DE' ] ) ) ).toEqual( [ 'German', 'French' ] );
	} );

	it( 'drops the second entry of a language once the list changes', async () => {
		const list = newWrapper( [ 'de', 'fr', 'de' ] );

		await press( list, 'Remove French' );

		expect( list.emitted( 'update:modelValue' ) ).toEqual( [ [ [ 'de' ] ] ] );
	} );

	it( 'lists a language by its name whatever the letter case of its tag', () => {
		expect( listedLanguages( newWrapper( [ 'de-AT' ] ) ) ).toEqual( [ 'Austrian German' ] );
	} );

	it( 'lists the language an empty list stands for: the content language, not the reader\'s', () => {
		wikiIn( 'fr', 'en' );

		expect( listedLanguages( newWrapper( [] ) ) ).toEqual( [ 'anglais' ] );
	} );

	it( 'keeps the content language an empty list stands for when a language is added', async () => {
		const list = newWrapper( [] );

		await searchFor( list, 'ital' );
		await offeredLanguage( list, 'Italian' ).trigger( 'click' );

		expect( list.emitted( 'update:modelValue' ) ).toEqual( [ [ [ 'en', 'it' ] ] ] );
	} );

	it( 'adds the language picked in the search at the end', async () => {
		const list = newWrapper( [ 'fr' ] );

		await searchFor( list, 'ital' );
		await offeredLanguage( list, 'Italian' ).trigger( 'click' );

		expect( listedLanguages( list ) ).toEqual( [ 'French', 'Italian' ] );
	} );

	it( 'counts a language it lists in other letter case as listed', async () => {
		const list = newWrapper( [ 'DE' ] );

		await searchFor( list, 'Germ' );

		expect( offeredLanguage( list, 'German' ).attributes( 'aria-disabled' ) ).toBe( 'true' );
	} );

	it( 'moves a language up', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Move Italian up' );

		expect( listedLanguages( list ) ).toEqual( [ 'French', 'Italian', 'German' ] );
	} );

	it( 'moves a language down', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Move French down' );

		expect( listedLanguages( list ) ).toEqual( [ 'German', 'French', 'Italian' ] );
	} );

	it( 'cannot move its first language up or its last one down', () => {
		const list = newWrapper( [ 'fr', 'de' ] );

		expect( button( list, 'Move French up' ).element.disabled ).toBe( true );
		expect( button( list, 'Move German down' ).element.disabled ).toBe( true );
	} );

	it( 'cannot remove its only language', () => {
		expect( button( newWrapper( [ 'fr' ] ), 'Remove French' ).element.disabled ).toBe( true );
	} );

	it( 'removes a language', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Remove German' );

		expect( listedLanguages( list ) ).toEqual( [ 'French', 'Italian' ] );
	} );

	it( 'keeps the focus on the button that moved the language', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Move Italian up' );

		expect( document.activeElement ).toBe( button( list, 'Move Italian up' ).element );
	} );

	it.each( [
		[ 'Move German up', 'Move German down' ],
		[ 'Move French down', 'Move French up' ],
	] )( 'hands the focus to the other move button once the language cannot move further: %s', async ( pressed, focused ) => {
		const list = newWrapper( [ 'fr', 'de' ] );

		await press( list, pressed );

		expect( document.activeElement ).toBe( button( list, focused ).element );
	} );

	it( 'hands the focus to the language that takes the place of a removed one', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Remove German' );

		expect( document.activeElement ).toBe( button( list, 'Remove Italian' ).element );
	} );

	it( 'hands the focus to the language before a removed last one', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Remove Italian' );

		expect( document.activeElement ).toBe( button( list, 'Remove German' ).element );
	} );

	it( 'hands the focus to the button that adds a language once only one is left', async () => {
		const list = newWrapper( [ 'fr', 'de' ] );

		await press( list, 'Remove German' );

		expect( document.activeElement ).toBe( addButton( list ).element );
	} );

	it( 'tells a screen reader what it added', async () => {
		const list = newWrapper( [ 'fr' ] );

		await searchFor( list, 'ital' );
		await offeredLanguage( list, 'Italian' ).trigger( 'click' );

		expect( announcement( list ) ).toBe( 'Italian added.' );
	} );

	it( 'tells a screen reader where it moved a language to', async () => {
		const list = newWrapper( [ 'fr', 'de', 'it' ] );

		await press( list, 'Move Italian up' );

		expect( announcement( list ) ).toBe( 'Italian moved to position 2 of 3.' );
	} );

	it( 'tells a screen reader what it removed', async () => {
		const list = newWrapper( [ 'fr', 'de' ] );

		await press( list, 'Remove German' );

		expect( announcement( list ) ).toBe( 'German removed.' );
	} );

	it( 'offers no way to change the list when disabled', () => {
		const list = newWrapper( [ 'fr', 'de' ], true );

		expect( list.findAll( 'button' ) ).toHaveLength( 0 );
		expect( listedLanguages( list ) ).toEqual( [ 'French', 'German' ] );
	} );

} );
