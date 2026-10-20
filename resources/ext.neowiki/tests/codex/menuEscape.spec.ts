import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, onTestFinished, vi } from 'vitest';
import { defineComponent } from 'vue';
import type { Component } from 'vue';
import { CdxDialog, CdxMenuButton, CdxSelect } from '@wikimedia/codex';
import {
	dialogOfAnotherExtension,
	guardMenuEscapes,
	listenForDialogKeys,
	openMenuAndPressEscape,
	pressKey,
	sendKey,
} from '../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

describe( 'keepMenuEscapesFromDialogs', () => {
	beforeEach( () => {
		guardMenuEscapes();
	} );

	function neoWikiDialog(): HTMLElement {
		return element( [ 'cdx-dialog', 'ext-neowiki-ui' ] );
	}

	function element( classes: string[] ): HTMLElement {
		const div = document.createElement( 'div' );
		div.classList.add( ...classes );
		document.body.append( div );
		onTestFinished( () => div.remove() );

		return div;
	}

	async function pressEscapeOn( host: Element, control: HTMLElement ): Promise<void> {
		focusIn( host, control );
		await pressKey( 'Escape' );
	}

	// Keys go where the focus is, so a control that takes none would leave a test pressing them elsewhere.
	function focusIn( host: Element, control: HTMLElement ): HTMLElement {
		host.append( control );

		control.focus();
		if ( document.activeElement !== control ) {
			throw new Error( 'The control took no focus: its host is not in the document.' );
		}

		return control;
	}

	// The control of a Codex menu: a combobox, expanded while the menu is open, which Codex closes on the Escape keydown.
	function menuControl( state: 'open' | 'closed' ): HTMLButtonElement {
		const control = document.createElement( 'button' );
		control.setAttribute( 'role', 'combobox' );
		control.setAttribute( 'aria-expanded', String( state === 'open' ) );
		control.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				control.setAttribute( 'aria-expanded', 'false' );
			}
		} );

		return control;
	}

	function closedMenuButtonToggle(): HTMLButtonElement {
		const toggle = document.createElement( 'button' );
		toggle.setAttribute( 'aria-haspopup', 'menu' );
		toggle.setAttribute( 'aria-expanded', 'false' );

		return toggle;
	}

	function openDisclosureToggle(): HTMLButtonElement {
		const toggle = document.createElement( 'button' );
		toggle.setAttribute( 'aria-expanded', 'true' );

		return toggle;
	}

	it( 'keeps the Escape that closes a menu from the dialog around it', async () => {
		const dialogKeyup = listenForDialogKeys();

		await pressEscapeOn( neoWikiDialog(), menuControl( 'open' ) );

		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'keeps a held Escape that closes a menu from the dialog', async () => {
		const dialogKeyup = listenForDialogKeys();
		focusIn( neoWikiDialog(), menuControl( 'open' ) );

		await sendKey( 'keydown', 'Escape' );
		await sendKey( 'keydown', 'Escape', { repeat: true } );
		await sendKey( 'keyup', 'Escape' );

		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'keeps the Escape that closes a menu from the dialog while another key goes down and up', async () => {
		const dialogKeyup = listenForDialogKeys();
		focusIn( neoWikiDialog(), menuControl( 'open' ) );

		await sendKey( 'keydown', 'Escape' );
		await sendKey( 'keydown', 'Tab' );
		await sendKey( 'keyup', 'Tab' );
		await sendKey( 'keyup', 'Escape' );

		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'keeps the Escape that closes a menu from the dialog when late results reopen the menu', async () => {
		const dialogKeyup = listenForDialogKeys();
		const control = focusIn( neoWikiDialog(), menuControl( 'open' ) );

		await sendKey( 'keydown', 'Escape' );
		control.setAttribute( 'aria-expanded', 'true' );
		await sendKey( 'keyup', 'Escape' );

		expect( dialogKeyup ).not.toHaveBeenCalled();
	} );

	it( 'lets the controls in the dialog handle the keyup of the Escape that closes a menu', async () => {
		const controlKeyup = vi.fn();
		const control = menuControl( 'open' );
		control.addEventListener( 'keyup', controlKeyup );

		await pressEscapeOn( neoWikiDialog(), control );

		expect( controlKeyup ).toHaveBeenCalledWith( expect.objectContaining( { key: 'Escape' } ) );
	} );

	it.each( [
		[ 'combobox', (): HTMLButtonElement => menuControl( 'closed' ) ],
		[ 'menu button', closedMenuButtonToggle ],
	] )( 'lets an Escape pressed on the closed menu of a %s reach the dialog', async ( _kind, closedControl ) => {
		const dialogKeyup = listenForDialogKeys();

		await pressEscapeOn( neoWikiDialog(), closedControl() );

		expect( dialogKeyup ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'lets an Escape that leaves a disclosure open reach the dialog', async () => {
		const dialogKeyup = listenForDialogKeys();

		await pressEscapeOn( neoWikiDialog(), openDisclosureToggle() );

		expect( dialogKeyup ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'lets the next Escape reach the dialog after a menu\'s Escape whose keyup went elsewhere', async () => {
		const dialog = neoWikiDialog();
		const control = focusIn( dialog, menuControl( 'open' ) );
		await sendKey( 'keydown', 'Escape' );
		control.blur();
		await sendKey( 'keyup', 'Escape' );
		const dialogKeyup = listenForDialogKeys();

		await pressEscapeOn( dialog, document.createElement( 'button' ) );

		expect( dialogKeyup ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'leaves the Escape that closes a menu in a dialog of other code alone', async () => {
		const dialogKeyup = listenForDialogKeys();

		await pressEscapeOn( dialogOfAnotherExtension(), menuControl( 'open' ) );

		expect( dialogKeyup ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'leaves the Escape that closes a menu outside a dialog alone', async () => {
		const dialogKeyup = listenForDialogKeys();

		await pressEscapeOn( element( [ 'ext-neowiki-ui' ] ), menuControl( 'open' ) );

		expect( dialogKeyup ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'in a Codex dialog', () => {
		function mountDialogWith( menuComponent: Component ): VueWrapper {
			return mount( defineComponent( {
				components: { CdxDialog, MenuComponent: menuComponent },
				setup: () => ( { items: [ { value: 'Person' }, { value: 'Company' } ] } ),
				template: `
					<CdxDialog :open="true" title="Dialog" class="ext-neowiki-ui">
						<MenuComponent :selected="null" :menu-items="items" />
						<button class="plain-button">Plain</button>
					</CdxDialog>
				`,
			} ) );
		}

		it.each( [
			[ 'select', CdxSelect, '[role="combobox"]' ],
			[ 'menu button', CdxMenuButton, 'button' ],
		] )( 'stays open on the Escape that closes the menu of a Codex %s in it', async ( _name, menuComponent, controlSelector ) => {
			const dialog = mountDialogWith( menuComponent );
			const trigger = dialog.findComponent( menuComponent ).find<HTMLElement>( controlSelector );

			await openMenuAndPressEscape( trigger.element );

			expect( trigger.attributes( 'aria-expanded' ) ).toBe( 'false' );
			expect( dialog.findComponent( CdxDialog ).emitted( 'update:open' ) ).toBeUndefined();
		} );

		it( 'closes on an Escape pressed outside a menu', async () => {
			const dialog = mountDialogWith( CdxSelect );

			// Teleported out of the wrapper, as Codex teleports every dialog.
			document.querySelector<HTMLElement>( '.plain-button' )!.focus();
			await pressKey( 'Escape' );

			expect( dialog.findComponent( CdxDialog ).emitted( 'update:open' ) ).toEqual( [ [ false ] ] );
		} );
	} );

} );
