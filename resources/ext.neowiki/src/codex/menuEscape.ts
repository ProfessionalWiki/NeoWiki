const NEOWIKI_UI = '.ext-neowiki-ui, .ext-neowiki-view';
const OPEN_MENU_CONTROL = '[aria-expanded="true"][role="combobox"], [aria-expanded="true"][aria-haspopup]';

/**
 * Codex: stops the Escape keydown that closes an open menu, but closes the dialog around it on the keyup.
 * Remove when: the Codex of the oldest MediaWiki NeoWiki supports keeps that keyup from its dialogs; not yet in
 *   Codex 2.7.0.
 *
 * Whether an Escape closes a menu is read on its first keydown, while the menu is open: by the keyup,
 * late results may have reopened it, and repeats or other keys pressed meanwhile find it closed. A
 * menu's control has a popup, as every combobox does; a disclosure toggle, which Escape leaves open,
 * has none. The keyup is stopped at the dialog, so whatever inside it handles Escape on its keyup still
 * does.
 */
export function keepMenuEscapesFromDialogs( signal?: AbortSignal ): void {
	let guardedDialog: HTMLElement | null = null;

	function stopEscape( event: KeyboardEvent ): void {
		if ( event.key === 'Escape' ) {
			event.stopPropagation();
			release();
		}
	}

	function release(): void {
		guardedDialog?.removeEventListener( 'keyup', stopEscape );
		guardedDialog = null;
	}

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Escape' || event.repeat ) {
			return;
		}

		release();
		guardedDialog = dialogAroundOpenMenu( event.target );
		guardedDialog?.addEventListener( 'keyup', stopEscape );
	}, { capture: true, signal } );
}

function dialogAroundOpenMenu( target: EventTarget | null ): HTMLElement | null {
	if ( !( target instanceof Element ) || !target.matches( OPEN_MENU_CONTROL ) || target.closest( NEOWIKI_UI ) === null ) {
		return null;
	}

	return target.closest<HTMLElement>( '.cdx-dialog' );
}
