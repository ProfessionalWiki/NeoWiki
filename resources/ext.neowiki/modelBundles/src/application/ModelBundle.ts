export type JsonObject = Record<string, unknown>;

/**
 * The kinds of item a file carries, in the order they are written: a Mapping exists before the
 * Subjects it projects are saved, so no graph store logs a missing one.
 */
export const ITEM_TYPES = [ 'schema', 'layout', 'mapping', 'subject' ] as const;

export type ItemType = typeof ITEM_TYPES[ number ];

/**
 * Empty strings stand for a version or description the file does not give.
 */
export interface BundleMetadata {
	readonly name: string;
	readonly version: string;
	readonly description: string;
}

export interface DependentSubjectEntry {
	readonly id: string;
	readonly schema: string;
	readonly statements: JsonObject;
}

/**
 * A Subject in the shape the REST create body takes, without relation IDs.
 */
export interface SubjectEntry {
	readonly schema: string;
	readonly label: string | null;
	readonly statements: JsonObject;
	readonly dependentSubjects: readonly DependentSubjectEntry[];
	/** Set only for a Subject that was the Main Subject of a main-namespace page. */
	readonly pageTitle: string | null;
}

/**
 * Without metadata, a plain export rather than a Model bundle. Schemas and Layouts are in the form NeoWiki
 * saves them in, so that two that read alike compare alike.
 */
export interface ModelBundle {
	readonly metadata: BundleMetadata | null;
	readonly schemas: ReadonlyMap<string, JsonObject>;
	readonly layouts: ReadonlyMap<string, JsonObject>;
	readonly mappings: ReadonlyMap<string, JsonObject>;
	readonly subjects: ReadonlyMap<string, SubjectEntry>;
}

export function itemId( type: ItemType, key: string ): string {
	return `${ type }:${ key }`;
}
