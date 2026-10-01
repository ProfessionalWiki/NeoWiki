import { mount, DOMWrapper, VueWrapper } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NeoPopover from '@/components/common/NeoPopover.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

/**
 * A host of the shape both real consumers have: a Codex button as the trigger, a field in the
 * panel. The trigger goes in as the component, which is what floating-ui needs to watch it.
 */
const PopoverHost = defineComponent( {
	components: { NeoPopover, CdxButton },
	props: { open: { type: Boolean, required: true } },
	emits: [ 'update:open' ],
	data(): { isOpen: boolean } {
		return { isOpen: this.open };
	},
	watch: {
		// Held here as a real consumer holds it: the popover asks, and the state answers. A host
		// that only listened would leave the panel open for ever, and the focus it keeps trying to
		// put in the panel would take it straight back off the trigger.
		isOpen( value: boolean ) {
			this.$emit( 'update:open', value );
		},
		open( value: boolean ) {
			this.isOpen = value;
		},
	},
	template: `
		<NeoPopover v-model:open="isOpen">
			<template #trigger="{ setTrigger, toggle, open: isExpanded, panelId }">
				<CdxButton
					class="trigger"
					:ref="setTrigger"
					:aria-expanded="isExpanded"
					:aria-controls="panelId"
					@click="toggle"
				>open</CdxButton>
			</template>
			<input class="in-panel">
		</NeoPopover>
	`,
} );

describe( 'NeoPopover', () => {
	let wrappers: VueWrapper[];

	function createWrapper( props: Record<string, unknown> = {} ): VueWrapper {
		const wrapper = mount( PopoverHost, {
			props: { open: false, ...props },
			global: { mocks: { $i18n } },
			attachTo: document.body,
		} );

		wrappers.push( wrapper );

		return wrapper;
	}

	function panel( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.ext-neowiki-popover__panel' );
	}

	function trigger( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.trigger' );
	}

	async function pressEscape( wrapper: VueWrapper, options: KeyboardEventInit = {} ): Promise<void> {
		const field = wrapper.find( '.in-panel' ).element as HTMLElement;

		// The popover puts the focus in the panel over the frames after it opens, so a key pressed
		// before that has settled is answered and then undone by a focus still on its way.
		await vi.waitFor( () => expect( document.activeElement ).toBe( field ) );

		field.dispatchEvent( new KeyboardEvent( 'keyup', {
			key: 'Escape', bubbles: true, cancelable: true, ...options,
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

	// Present either way, since useFloatingMenu places an element that is already there.
	it( 'keeps the panel out of sight while it is closed', () => {
		expect( panel( createWrapper() ).isVisible() ).toBe( false );
	} );

	it( 'shows the panel once it is open', () => {
		expect( panel( createWrapper( { open: true } ) ).isVisible() ).toBe( true );
	} );

	it( 'asks to open when the trigger is used', async () => {
		const wrapper = createWrapper();

		await trigger( wrapper ).trigger( 'click' );

		expect( lastOpenState( wrapper ) ).toBe( true );
	} );

	it( 'asks to close when the trigger is used again', async () => {
		const wrapper = createWrapper( { open: true } );

		await trigger( wrapper ).trigger( 'click' );

		expect( lastOpenState( wrapper ) ).toBe( false );
	} );

	// Codex dialogs close on Escape's keyup, so an Escape caught only on keydown still reaches
	// the dialog and takes everything typed into it.
	it( 'closes on the escape keyup and hands focus back to the trigger', async () => {
		const wrapper = createWrapper( { open: true } );

		await pressEscape( wrapper );

		expect( lastOpenState( wrapper ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger( wrapper ).element );
	} );

	it( 'keeps that escape to itself', async () => {
		const wrapper = createWrapper( { open: true } );
		let reachedTheDialog = false;
		const listener = (): void => {
			reachedTheDialog = true;
		};
		document.body.addEventListener( 'keyup', listener );

		await pressEscape( wrapper );
		document.body.removeEventListener( 'keyup', listener );

		expect( reachedTheDialog ).toBe( false );
	} );

	// The escape that cancels an IME composition is not a cancel.
	it( 'leaves a composing escape alone', async () => {
		const wrapper = createWrapper( { open: true } );

		await pressEscape( wrapper, { isComposing: true } );

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
	} );

	it( 'closes when the pointer goes down outside it', async () => {
		const wrapper = createWrapper( { open: true } );

		document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		await nextTick();

		expect( lastOpenState( wrapper ) ).toBe( false );
	} );

	it( 'stays open while the panel itself is being used', async () => {
		const wrapper = createWrapper( { open: true } );

		panel( wrapper ).element.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		await nextTick();

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
	} );

	it( 'listens for an outside press again each time it opens', async () => {
		const wrapper = createWrapper( { open: true } );
		await wrapper.setProps( { open: false } );
		await wrapper.setProps( { open: true } );

		document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		await nextTick();

		expect( lastOpenState( wrapper ) ).toBe( false );
	} );

	it( 'closes when the focus moves on to somewhere else', async () => {
		const wrapper = createWrapper( { open: true } );

		await panel( wrapper ).trigger( 'focusout', { relatedTarget: document.body } );

		expect( lastOpenState( wrapper ) ).toBe( false );
	} );

	// Focus going nowhere is the window losing it, and the panel waits for the user's return.
	it( 'stays open while the user is away in another tab or application', async () => {
		const wrapper = createWrapper( { open: true } );

		await panel( wrapper ).trigger( 'focusout', { relatedTarget: null } );

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
	} );

	// Whatever the host put in the panel is what the user opened it for.
	it( 'puts the user in the panel when it opens', async () => {
		const wrapper = createWrapper();

		await wrapper.setProps( { open: true } );

		await vi.waitFor( () => expect( document.activeElement )
			.toBe( wrapper.find( '.in-panel' ).element ) );
	} );

	/**
	 * A host that closes the panel itself - once a choice made in it has been taken, say - is
	 * closing it for the user, so the focus comes back as it does from escape. Without this the
	 * panel is hidden out from under the focus, which lands on the document body.
	 */
	it( 'hands focus back when its host closes it', async () => {
		const wrapper = createWrapper( { open: true } );
		const field = wrapper.find( '.in-panel' ).element as HTMLElement;
		await vi.waitFor( () => expect( document.activeElement ).toBe( field ) );

		await wrapper.setProps( { open: false } );

		expect( document.activeElement ).toBe( trigger( wrapper ).element );
	} );

	// Focus that has already gone elsewhere was the user leaving, and is not to be taken back.
	it( 'leaves focus alone when it closes because focus left it', async () => {
		const wrapper = createWrapper( { open: true } );
		const outside = document.createElement( 'button' );
		document.body.appendChild( outside );
		// The panel spends the frames after it opens putting the focus in itself, so focus moved
		// before that has settled is taken straight back.
		await vi.waitFor( () => expect( document.activeElement )
			.toBe( wrapper.find( '.in-panel' ).element ) );
		outside.focus();

		await panel( wrapper ).trigger( 'focusout', { relatedTarget: outside } );
		await wrapper.setProps( { open: false } );

		expect( document.activeElement ).toBe( outside );
		outside.remove();
	} );

	it( 'names the panel the trigger controls', () => {
		const wrapper = createWrapper( { open: true } );

		expect( trigger( wrapper ).attributes( 'aria-controls' ) )
			.toBe( panel( wrapper ).attributes( 'id' ) );
	} );

	/**
	 * Scrolling what the popover sits in takes the trigger out from under the panel, and once the
	 * trigger leaves the visible area useFloatingMenu hides the panel, dropping the focus in it.
	 * The panel closes first instead, which hands the focus back.
	 */
	it( 'closes when something it sits inside is scrolled', async () => {
		const wrapper = createWrapper( { open: true } );

		document.body.dispatchEvent( new Event( 'scroll' ) );
		await nextTick();

		expect( lastOpenState( wrapper ) ).toBe( false );
	} );

	// Scrolling the panel's own list is the user reading it, not the page moving under them.
	it( 'stays open while the list inside it is scrolled', async () => {
		const wrapper = createWrapper( { open: true } );

		panel( wrapper ).element.dispatchEvent( new Event( 'scroll' ) );
		await nextTick();

		expect( wrapper.emitted( 'update:open' ) ).toBeUndefined();
	} );
} );
