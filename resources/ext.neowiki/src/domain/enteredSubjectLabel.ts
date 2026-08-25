/**
 * What a label field's text means for storage: a field left blank, or holding only whitespace,
 * is no label rather than an empty one, so the Subject falls back to a computed name.
 */
export function enteredSubjectLabel( text: string ): string | null {
	return text.trim() || null;
}
