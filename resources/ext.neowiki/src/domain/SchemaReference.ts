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
 * The Schema's name without its Source, for the code that knows a Schema by its bare name.
 */
export function schemaReferenceName( reference: SchemaReference ): string {
	return typeof reference === 'string' ? reference : reference.name;
}

/**
 * How a reference reads to a person: a Schema of another Source is prefixed with its source key. Not
 * a serialization: `sourceKey:Name` is ambiguous with a colon-bearing local name, so nothing parses it.
 *
 * Mirrors SchemaReference::getText() in src/Domain/Schema/SchemaReference.php.
 */
export function schemaReferenceText( reference: SchemaReference ): string {
	return typeof reference === 'string' ? reference : `${ reference.source }:${ reference.name }`;
}

/**
 * Whether the reference names a Schema of this wiki, which is the only kind anything here can
 * resolve, search against, or offer in a picker.
 */
export function isLocalSchemaReference( reference: SchemaReference ): reference is string {
	return typeof reference === 'string';
}
