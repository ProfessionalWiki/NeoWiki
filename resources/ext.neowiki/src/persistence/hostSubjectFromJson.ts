import type { HostSubject } from '@/domain/HostSubject';
import { type SchemaReference, schemaReferenceName } from '@/domain/SchemaReference';

export interface HostSubjectJson {
	id: string;
	schema: SchemaReference;
	displayName: string;
	displayNameIsGenerated: boolean;
	hostSubject?: HostSubjectJson;
}

export function hostSubjectFromJson( json: HostSubjectJson | undefined ): HostSubject | null {
	if ( json === undefined ) {
		return null;
	}

	return {
		id: json.id,
		schemaName: schemaReferenceName( json.schema ),
		displayName: json.displayName,
		displayNameIsGenerated: json.displayNameIsGenerated,
		hostSubject: hostSubjectFromJson( json.hostSubject ),
	};
}
