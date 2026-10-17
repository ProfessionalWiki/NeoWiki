type MountedTextInput = { $el: Element };

/**
 * Whether the browser reports a CdxTextInput's native input as empty only because it cannot turn what
 * the field shows into a value, such as "-" in a number field or a date with one part cleared.
 */
export function holdsUnparseableInput( textInput: MountedTextInput | null ): boolean {
	return textInput?.$el.querySelector( 'input' )?.validity.badInput ?? false;
}

/**
 * For a date field's keyup. Clearing the last part of a partly cleared date fires no input event, so
 * `update` gets the empty text when a deletion key leaves the field empty while `text`, what it held,
 * was not. Other keys do nothing: a field also reads as empty when the browser cannot show its date.
 */
export function updateIfEmptied( event: KeyboardEvent, text: string, update: ( text: string ) => void ): void {
	const input = event.target as HTMLInputElement;

	if ( isDeletion( event ) && text !== '' && input.value === '' && !input.validity.badInput ) {
		update( '' );
	}
}

function isDeletion( event: KeyboardEvent ): boolean {
	return event.key === 'Backspace' || event.key === 'Delete';
}
