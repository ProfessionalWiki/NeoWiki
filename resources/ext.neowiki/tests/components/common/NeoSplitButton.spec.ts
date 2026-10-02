import { mount, DOMWrapper, VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NeoSplitButton from '@/components/common/NeoSplitButton.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'NeoSplitButton', () => {
	let wrappers: VueWrapper[];

	function createWrapper( props: Record<string, unknown> = {} ): VueWrapper {
		const wrapper = mount( NeoSplitButton, {
			props: { label: 'Create on this page', toggleLabel: 'Change destination', open: false, ...props },
			slots: { default: '<input class="in-popover">' },
			global: { mocks: { $i18n } },
			attachTo: document.body,
		} );

		wrappers.push( wrapper );

		return wrapper;
	}

	function action( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.ext-neowiki-split-button__action' );
	}

	function toggle( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.ext-neowiki-split-button__toggle' );
	}

	async function pressEscape( wrapper: VueWrapper ): Promise<void> {
		const field = wrapper.find( '.in-popover' ).element as HTMLElement;

		await vi.waitFor( () => expect( document.activeElement ).toBe( field ) );

		field.dispatchEvent( new KeyboardEvent( 'keyup', {
			key: 'Escape', bubbles: true, cancelable: true,
		} ) );
		await nextTick();
	}

	function lastOpenState( wrapper: VueWrapper ): unknown {
		const events = wrapper.emitted( 'update:open' ) ?? [];
		return events[ events.length - 1 ]?.[ 0 ];
	}

	beforeEach( () => {
		setupMwMock();
		wrappers = [];
	} );

	afterEach( () => {
		wrappers.forEach( ( wrapper ) => wrapper.unmount() );
	} );

	it( 'names the action it performs', () => {
		expect( action( createWrapper() ).text() ).toContain( 'Create on this page' );
	} );

	it( 'performs that action when the action half is clicked', async () => {
		const wrapper = createWrapper();

		await action( wrapper ).trigger( 'click' );

		expect( wrapper.emitted( 'click' ) ).toHaveLength( 1 );
	} );

	it( 'asks to open when the toggle is clicked', async () => {
		const wrapper = createWrapper();

		await toggle( wrapper ).trigger( 'click' );

		expect( lastOpenState( wrapper ) ).toBe( true );
	} );

	it( 'asks to close when the toggle is clicked again', async () => {
		const wrapper = createWrapper( { open: true } );

		await toggle( wrapper ).trigger( 'click' );

		expect( lastOpenState( wrapper ) ).toBe( false );
	} );

	it( 'opens on the down arrow, the way a menu trigger does', async () => {
		const wrapper = createWrapper();

		await toggle( wrapper ).trigger( 'keydown', { key: 'ArrowDown' } );

		expect( lastOpenState( wrapper ) ).toBe( true );
	} );

	// Present either way, since useFloatingMenu places an element that is already there.
	// Whatever was typed into a popover that was dismissed is not an answer to come back to.
	it( 'builds its content afresh on each opening', async () => {
		const wrapper = createWrapper();
		const first = wrapper.find( '.in-popover' ).element;

		await wrapper.setProps( { open: true } );
		await wrapper.setProps( { open: false } );
		await wrapper.setProps( { open: true } );

		expect( wrapper.find( '.in-popover' ).element ).not.toBe( first );
	} );

	// The action half performs an action; only the toggle owns a popover.
	it( 'says the toggle is what is expanded', async () => {
		const wrapper = createWrapper( { open: true } );

		expect( toggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'names the panel the toggle controls', async () => {
		const wrapper = createWrapper( { open: true } );

		expect( toggle( wrapper ).attributes( 'aria-controls' ) )
			.toBe( wrapper.find( '.ext-neowiki-popover__panel' ).attributes( 'id' ) );
	} );

	/**
	 * The panel hangs off the toggle, not off the whole control: anchored to the action half it
	 * would be placed against the wrong edge, and the focus that comes back when it closes would
	 * land on the action rather than on what opened it.
	 */
	it( 'hangs the panel off the toggle', async () => {
		const wrapper = createWrapper( { open: true } );

		await pressEscape( wrapper );
		// Answering as the host does, since the popover only asks to be closed.
		await wrapper.setProps( { open: false } );

		expect( document.activeElement ).toBe( toggle( wrapper ).element );
	} );

	it( 'refuses both halves while nothing here can be done at all', () => {
		const wrapper = createWrapper( { disabled: true } );

		expect( action( wrapper ).attributes( 'disabled' ) ).toBeDefined();
		expect( toggle( wrapper ).attributes( 'disabled' ) ).toBeDefined();
	} );

	it( 'refuses the action alone when only the action cannot be performed', () => {
		const wrapper = createWrapper( { actionDisabled: true } );

		expect( action( wrapper ).attributes( 'disabled' ) ).toBeDefined();
	} );

	/**
	 * What the popover holds is often what makes the action performable - the destination the
	 * action is waiting for. Switching the toggle off along with the action would shut the only
	 * way out of that state.
	 */
	it( 'keeps the toggle reachable when only the action cannot be performed', () => {
		const wrapper = createWrapper( { actionDisabled: true } );

		expect( toggle( wrapper ).attributes( 'disabled' ) ).toBeUndefined();
	} );
} );
