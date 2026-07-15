import type { SchemaName } from '@/domain/Schema';

/**
 * A reference to a Schema from a Source other than the local wiki (ADR 23). Schema names are page
 * titles and may contain colons, so the source and name are kept as separate fields rather than
 * concatenated into one string.
 */
export interface ForeignSchemaReference {
	readonly source: string;
	readonly name: SchemaName;
}

/**
 * A reference to a Schema, resolved through its own Source. A bare name string is a local reference;
 * an object is a reference to a Schema from another Source. Local references stay plain strings end
 * to end.
 */
export type SchemaReference = SchemaName | ForeignSchemaReference;

/**
 * The Schema's name, regardless of Source. A local reference is already its name; an object
 * reference (including one naming the local Source) reduces to its name.
 */
export function schemaReferenceName( reference: SchemaReference ): SchemaName {
	return typeof reference === 'string' ? reference : reference.name;
}
