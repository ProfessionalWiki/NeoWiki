import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount, VueWrapper } from '@vue/test-utils';
import PaneDivider from '@/components/common/PaneDivider.vue';

const PROPS = {
	size: 320,
	min: 192,
	max: 800,
	label: 'Resize the list',
	controls: 'pane-1',
};

// Attached to the document so that focus works and so that `direction` resolves through
// inheritance, which is the only way it is ever set in production.
function mountDivider( props: Partial<typeof PROPS> & { disabled?: boolean } = {} ): VueWrapper {
	const wrapper = mount( PaneDivider, {
		props: { ...PROPS, ...props },
		attachTo: document.body,
	} );

	// jsdom implements no pointer capture.
	( wrapper.element as HTMLElement ).setPointerCapture = vi.fn();

	return wrapper;
}

function lastResize( wrapper: VueWrapper ): number | undefined {
	const events = wrapper.emitted( 'resize' );
	return events === undefined ? undefined : ( events[ events.length - 1 ][ 0 ] as number );
}

// Dispatched rather than triggered: test-utils builds a MouseEvent whose `button` is
// read-only.
async function pointer(
	wrapper: VueWrapper,
	type: string,
	init: { button?: number; pointerId?: number; clientX?: number; buttons?: number } = {},
): Promise<PointerEvent> {
	const event = new PointerEvent( type, {
		bubbles: true,
		cancelable: true,
		button: init.button ?? 0,
		// Held for the press and the moves, released by the time the pointer comes up.
		buttons: init.buttons ?? ( type === 'pointermove' || type === 'pointerdown' ? 1 : 0 ),
		clientX: init.clientX ?? 0,
		pointerId: init.pointerId ?? 1,
		pointerType: 'mouse',
	} );

	wrapper.element.dispatchEvent( event );
	await nextTick();

	return event;
}

function key( wrapper: VueWrapper, name: string, init: KeyboardEventInit = {} ): KeyboardEvent {
	const event = new KeyboardEvent( 'keydown', { key: name, bubbles: true, cancelable: true, ...init } );
	wrapper.element.dispatchEvent( event );

	return event;
}

async function drag( wrapper: VueWrapper, from: number, to: number ): Promise<void> {
	await pointer( wrapper, 'pointerdown', { clientX: from } );
	await pointer( wrapper, 'pointermove', { clientX: to } );
}

describe( 'PaneDivider', () => {

	describe( 'the separator it exposes', () => {

		it( 'is a separator', () => {
			expect( mountDivider().attributes( 'role' ) ).toBe( 'separator' );
		} );

		it( 'is reachable by keyboard', () => {
			expect( mountDivider().attributes( 'tabindex' ) ).toBe( '0' );
		} );

		it( 'declares the vertical orientation, which is not the role default', () => {
			expect( mountDivider().attributes( 'aria-orientation' ) ).toBe( 'vertical' );
		} );

		it( 'carries the name the caller gave it', () => {
			expect( mountDivider().attributes( 'aria-label' ) ).toBe( 'Resize the list' );
		} );

		it( 'points at the pane it sizes', () => {
			expect( mountDivider().attributes( 'aria-controls' ) ).toBe( 'pane-1' );
		} );

		it( 'announces the current position and its bounds', () => {
			const wrapper = mountDivider();

			expect( wrapper.attributes( 'aria-valuenow' ) ).toBe( '320' );
			expect( wrapper.attributes( 'aria-valuemin' ) ).toBe( '192' );
			expect( wrapper.attributes( 'aria-valuemax' ) ).toBe( '800' );
		} );

		it( 'is not marked disabled while it can move', () => {
			expect( mountDivider().attributes( 'aria-disabled' ) ).toBeUndefined();
		} );

		it( 'is marked disabled where there is no room to move', () => {
			expect( mountDivider( { disabled: true } ).attributes( 'aria-disabled' ) ).toBe( 'true' );
		} );
	} );

	describe( 'the keyboard', () => {

		it( 'widens the pane on the arrow away from it', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );

			expect( lastResize( wrapper ) ).toBe( 336 );
		} );

		it( 'narrows the pane on the arrow towards it', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( lastResize( wrapper ) ).toBe( 304 );
		} );

		it( 'takes a larger step on Page keys', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'PageUp' } );

			expect( lastResize( wrapper ) ).toBe( 448 );
		} );

		it( 'takes a larger step back on the other Page key', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'PageDown' } );

			expect( lastResize( wrapper ) ).toBe( 192 );
		} );

		it( 'leaves a modified arrow to the browser', async () => {
			const wrapper = mountDivider();

			const event = key( wrapper, 'ArrowLeft', { altKey: true } );

			expect( event.defaultPrevented ).toBe( false );
			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'goes to the smallest allowed size on Home', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'Home' } );

			expect( lastResize( wrapper ) ).toBe( 192 );
		} );

		it( 'goes to the largest allowed size on End', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'End' } );

			expect( lastResize( wrapper ) ).toBe( 800 );
		} );

		it( 'commits once the key is let go', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );
			expect( wrapper.emitted( 'commit' ) ).toBeUndefined();

			await wrapper.trigger( 'keyup', { key: 'ArrowRight' } );
			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );

		// Key repeat sends a keydown per step and one keyup at the end.
		it( 'commits once for a held key, however many steps it took', async () => {
			const wrapper = mountDivider();

			for ( let step = 0; step < 5; step++ ) {
				await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );
			}
			await wrapper.trigger( 'keyup', { key: 'ArrowRight' } );

			expect( wrapper.emitted( 'resize' ) ).toHaveLength( 5 );
			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );

		it( 'commits nothing when a key it ignores is let go', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'Escape' } );
			await wrapper.trigger( 'keyup', { key: 'Escape' } );

			expect( wrapper.emitted( 'commit' ) ).toBeUndefined();
		} );

		it( 'leaves the vertical arrows to the surface behind it', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowDown' } );

			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'leaves Escape alone, so the dialog still closes on it', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'Escape' } );

			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'does not move where there is no room to move', async () => {
			const wrapper = mountDivider( { disabled: true } );

			await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );

			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );
	} );

	describe( 'the pointer', () => {

		it( 'follows the pointer', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );

			expect( lastResize( wrapper ) ).toBe( 380 );
		} );

		// The host applies each size as it arrives, and may grant less than was asked for.
		// Measuring the next move against what it granted would make the pane drift away
		// from the pointer; the origin of the gesture is the only stable reference.
		it( 'measures every move from where the drag began, not from the size granted', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			await wrapper.setProps( { size: 350 } );
			await pointer( wrapper, 'pointermove', { clientX: 520 } );

			expect( lastResize( wrapper ) ).toBe( 340 );
		} );

		it( 'ignores a pointer that never went down on it', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointermove', { clientX: 560 } );

			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'ignores a button that is not the primary one', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointerdown', { button: 2, clientX: 500 } );
			await pointer( wrapper, 'pointermove', { clientX: 560 } );

			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'does not drag where there is no room to move', async () => {
			const wrapper = mountDivider( { disabled: true } );

			await drag( wrapper, 500, 560 );

			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'takes the pointer so the drag survives leaving the divider', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointerdown', { clientX: 500 } );

			expect( wrapper.element.setPointerCapture ).toHaveBeenCalledWith( 1 );
		} );

		it( 'commits once the drag ends', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			await pointer( wrapper, 'pointerup' );

			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );

		it( 'commits when the gesture is taken away', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			await pointer( wrapper, 'pointercancel' );

			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );

		// Without pointer capture the release can land elsewhere, so a move with nothing
		// held is the only sign the drag is over.
		it( 'ends a drag the release never reported', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			await pointer( wrapper, 'pointermove', { clientX: 600, buttons: 0 } );

			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
			expect( lastResize( wrapper ) ).toBe( 380 );
		} );

		it( 'stops following once the drag has ended', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			await pointer( wrapper, 'pointerup' );
			await pointer( wrapper, 'pointermove', { clientX: 700 } );

			expect( lastResize( wrapper ) ).toBe( 380 );
		} );
	} );

	describe( 'the gestures it does not treat as a change', () => {

		it( 'commits nothing for a press that moved nothing', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointerdown', { clientX: 500 } );
			await pointer( wrapper, 'pointerup' );

			expect( wrapper.emitted( 'commit' ) ).toBeUndefined();
		} );

		it( 'commits a gesture the reader walked away from', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );
			await wrapper.trigger( 'blur' );

			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );

		it( 'commits nothing on a later keyup once it has walked away', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );
			await wrapper.trigger( 'blur' );
			await wrapper.trigger( 'keyup', { key: 'Shift' } );

			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );

		// Swallowing a key it will not act on would leave the reader unable to page the
		// surface behind it while the divider happens to hold focus.
		it( 'leaves the paging keys to the surface behind it when it cannot move', async () => {
			const wrapper = mountDivider( { disabled: true } );

			const event = key( wrapper, 'PageUp' );

			expect( event.defaultPrevented ).toBe( false );
			expect( wrapper.emitted( 'resize' ) ).toBeUndefined();
		} );

		it( 'consumes the paging keys it does act on', async () => {
			const wrapper = mountDivider();

			const event = key( wrapper, 'PageUp' );

			expect( event.defaultPrevented ).toBe( true );
		} );
	} );

	describe( 'while a drag is live', () => {

		it( 'takes the press, so no text selection begins under it', async () => {
			const wrapper = mountDivider();

			const event = await pointer( wrapper, 'pointerdown', { clientX: 500 } );

			expect( event.defaultPrevented ).toBe( true );
		} );

		it( 'puts focus on itself, the press having withheld it', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointerdown', { clientX: 500 } );

			expect( document.activeElement ).toBe( wrapper.element );
		} );

		it( 'marks the page, so the cursor holds wherever the pointer goes', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointerdown', { clientX: 500 } );

			expect( document.documentElement.classList.contains( 'ext-neowiki-pane-resizing' ) ).toBe( true );
		} );

		it( 'unmarks the page once the drag ends', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			await pointer( wrapper, 'pointerup' );

			expect( document.documentElement.classList.contains( 'ext-neowiki-pane-resizing' ) ).toBe( false );
		} );

		it( 'unmarks the page when it is torn down mid-drag', async () => {
			const wrapper = mountDivider();

			await pointer( wrapper, 'pointerdown', { clientX: 500 } );
			wrapper.unmount();

			expect( document.documentElement.classList.contains( 'ext-neowiki-pane-resizing' ) ).toBe( false );
		} );

		// A dialog closed on Escape mid-drag takes the divider with it before any pointerup.
		it( 'commits a drag cut short by its own teardown', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 560 );
			wrapper.unmount();

			expect( wrapper.emitted( 'commit' ) ).toHaveLength( 1 );
		} );
	} );

	// Set on an ancestor rather than on the divider, because that is where it comes from:
	// the interface direction is inherited, and a test that put it inline here would pass
	// against an implementation that never consulted the cascade at all.
	describe( 'in a right-to-left interface', () => {

		beforeEach( () => {
			document.body.style.direction = 'rtl';
		} );

		afterEach( () => {
			document.body.style.direction = '';
		} );

		it( 'grows the pane as the pointer moves the other way', async () => {
			const wrapper = mountDivider();

			await drag( wrapper, 500, 440 );

			expect( lastResize( wrapper ) ).toBe( 380 );
		} );

		it( 'mirrors the arrow keys, which CSSJanus cannot reach', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'ArrowRight' } );

			expect( lastResize( wrapper ) ).toBe( 304 );
		} );

		it( 'leaves Home and End in value space, unmirrored', async () => {
			const wrapper = mountDivider();

			await wrapper.trigger( 'keydown', { key: 'Home' } );

			expect( lastResize( wrapper ) ).toBe( 192 );
		} );
	} );
} );

describe( 'PaneDivider when pointer capture is refused', () => {

	// A browser throws NotFoundError for a pointer it no longer counts as active. Capture
	// only widens where the moves arrive, so losing it must not lose the drag.
	it( 'still resizes when the browser refuses the capture', async () => {
		const wrapper = mount( PaneDivider, { props: PROPS, attachTo: document.body } );
		( wrapper.element as HTMLElement ).setPointerCapture = () => {
			throw new DOMException( 'no active pointer', 'NotFoundError' );
		};

		await drag( wrapper, 500, 560 );

		expect( lastResize( wrapper ) ).toBe( 380 );
	} );
} );
