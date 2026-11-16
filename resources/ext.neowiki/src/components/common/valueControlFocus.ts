// Not every tab stop: a chip takes the focus too, and Backspace on it removes its value.
const VALUE_CONTROL = 'input, select, textarea, [role="combobox"]';

/**
 * Focuses the container's first value control, or the container itself where there is none or that control is
 * a lookup, which would open its menu on focus.
 */
export function focusFirstValueControl( container: HTMLElement, options?: FocusOptions ): void {
	const control = container.querySelector<HTMLElement>( VALUE_CONTROL );

	( control === null || control.matches( 'input[role="combobox"]' ) ? container : control ).focus( options );
}
