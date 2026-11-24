import { onBeforeUnmount, Ref, watch } from 'vue';

interface AfterPress {
	afterPress: ( action: () => void ) => void;
	cancel: () => void;
}

/**
 * Re-laying out a field mid-press, by swapping it for text or taking part of it away, moves
 * what is around it, and the release then lands away from what was pressed: on a dialog's
 * backdrop instead of Save, or beside a select that moved up. While `watching` is true,
 * `afterPress` runs an action at once, or during a press one task past the release, so the
 * click the press produces is dispatched first. `cancel` drops an action still waiting.
 */
export function useAfterPress( watching: Ref<boolean> ): AfterPress {
	let pressed = false;
	let waiting: ( () => void ) | null = null;
	let timer: ReturnType<typeof setTimeout> | undefined;

	function onPointerDown(): void {
		pressed = true;
	}

	function onPointerUp(): void {
		pressed = false;

		if ( waiting !== null ) {
			timer = setTimeout( waiting );
			waiting = null;
		}
	}

	function watchPresses(): void {
		pressed = false;
		waiting = null;
		document.addEventListener( 'pointerdown', onPointerDown, true );
		document.addEventListener( 'pointerup', onPointerUp, true );
		document.addEventListener( 'pointercancel', onPointerUp, true );
	}

	function stopWatchingPresses(): void {
		document.removeEventListener( 'pointerdown', onPointerDown, true );
		document.removeEventListener( 'pointerup', onPointerUp, true );
		document.removeEventListener( 'pointercancel', onPointerUp, true );
	}

	function cancel(): void {
		waiting = null;
		clearTimeout( timer );
	}

	watch( watching, ( isWatching ) => {
		if ( isWatching ) {
			watchPresses();
		} else {
			stopWatchingPresses();
		}
	} );

	onBeforeUnmount( () => {
		stopWatchingPresses();
		cancel();
	} );

	return {
		afterPress: ( action: () => void ): void => {
			if ( pressed ) {
				waiting = action;
			} else {
				action();
			}
		},
		cancel,
	};
}
