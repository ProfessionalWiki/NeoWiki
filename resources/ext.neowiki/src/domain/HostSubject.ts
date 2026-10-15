import type { SchemaName } from '@/domain/Schema';

/**
 * The fields a Subject is named by, as the REST API serves them for a Subject, a summary or a Host Subject.
 */
export interface SubjectNameFields {
	readonly id: string;
	readonly schemaName: SchemaName;
	readonly displayName: string;
	readonly displayNameIsGenerated: boolean;
	/** Null except for a Dependent Subject nobody named. */
	readonly hostSubject: HostSubject | null;
}

/**
 * The Host Subject a Dependent Subject is named after, as in "Birth of Pablo Picasso".
 */
export type HostSubject = SubjectNameFields;
