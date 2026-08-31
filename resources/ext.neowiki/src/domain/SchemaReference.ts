/**
 * How a Schema is named in the data the server sends (ADR 23). A bare string is always a Schema of
 * this wiki; a Schema from another Source arrives as an object, which no local name can be mistaken
 * for since a local name may itself contain a colon.
 *
 * The value is never rewritten here, so whatever the server sent goes back unchanged.
 *
 * Mirrors src/Domain/Schema/SchemaReference.php.
 */
export type SchemaReference = string | { readonly source: string; readonly name: string };

/**
 * The Schema's name without its Source, for the surfaces that name a Schema of this wiki: the
 * Schema picker's selection and the local Schema lookups behind it.
 */
export function schemaReferenceName( reference: SchemaReference | undefined ): string {
	if ( reference === undefined ) {
		return '';
	}

	return typeof reference === 'string' ? reference : reference.name;
}

/**
 * Whether the reference names a Schema of this wiki, which is the only kind anything here can
 * resolve, search against, or offer in a picker.
 */
export function isLocalSchemaReference( reference: SchemaReference | undefined ): boolean {
	return typeof reference === 'string';
}

/**
 * The reference as a person reads it. A Source-qualified reference shows its Source so it is not
 * silently displayed as a local Schema of the same name.
 */
export function schemaReferenceText( reference: SchemaReference | undefined ): string {
	if ( reference === undefined ) {
		return '';
	}

	return typeof reference === 'string' ? reference : `${ reference.source }:${ reference.name }`;
}
