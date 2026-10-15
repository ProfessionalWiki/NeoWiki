export interface ListEscapeGuard {
	/** For the keydown capture of an element around the control that opens the list. */
	noteEscape( event: KeyboardEvent ): void;
	/** For the Escape keyup of that same element. */
	keepEscapeFromDialog( event: KeyboardEvent ): void;
}

/**
 * Codex dialogs close on the Escape keyup, which would take everything typed into the dialog with it,
 * so the Escape that closes an open list goes no further. Whether it does is read on the keydown, while
 * the list is still open.
 */
export function useListEscapeGuard(): ListEscapeGuard {
	let escapeClosesList = false;

	return {
		noteEscape( event: KeyboardEvent ): void {
			escapeClosesList = event.key === 'Escape' &&
				event.target instanceof Element &&
				event.target.getAttribute( 'aria-expanded' ) === 'true';
		},
		keepEscapeFromDialog( event: KeyboardEvent ): void {
			if ( escapeClosesList ) {
				event.stopPropagation();
				escapeClosesList = false;
			}
		},
	};
}
