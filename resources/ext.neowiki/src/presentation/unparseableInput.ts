type MountedTextInput = { $el: Element };

/**
 * Whether the browser reports a CdxTextInput's native input as empty only because it cannot turn what
 * the field shows into a value, such as "-" in a number field or a date with one part cleared.
 */
export function holdsUnparseableInput( textInput: MountedTextInput | null ): boolean {
	return nativeInput( textInput )?.validity.badInput ?? false;
}

/**
 * Hands `update` the empty text once the field reads as empty while `text`, what it last held, does not.
 * For a date field's keyup: clearing the last part of a partly cleared date fires no input event.
 */
export function updateIfEmptied( textInput: MountedTextInput | null, text: string, update: ( text: string ) => void ): void {
	const input = nativeInput( textInput );

	if ( text !== '' && input !== null && input.value === '' && !input.validity.badInput ) {
		update( '' );
	}
}

function nativeInput( textInput: MountedTextInput | null ): HTMLInputElement | null {
	return textInput?.$el.querySelector( 'input' ) ?? null;
}
