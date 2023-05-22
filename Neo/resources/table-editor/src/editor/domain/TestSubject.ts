import { PageIdentifiers, Subject } from '@/editor/domain/Subject';
import type { SubjectProperties } from '@/editor/domain/Subject';
import { SubjectId } from '@/editor/domain/SubjectId';

export const ZERO_GUID = '00000000-0000-0000-0000-000000000000';
export const DEFAULT_TEST_SUBJECT_LABEL = 'Test subject';
export const DEFAULT_TEST_SCHEMA_ID = 'TestSchema';

interface NewTestSubjectOptions {
	id?: string|SubjectId;
	label?: string;
	schemaId?: string;
	properties?: SubjectProperties;
	pageIdentifiers?: PageIdentifiers;
}

export function newTestSubject( {
	id = ZERO_GUID,
	label = DEFAULT_TEST_SUBJECT_LABEL,
	schemaId = DEFAULT_TEST_SCHEMA_ID,
	properties = {},
	pageIdentifiers = new PageIdentifiers( 0, 'TestSubjectPage' )
}: NewTestSubjectOptions = {} ): Subject {
	return new Subject(
		id instanceof SubjectId ? id : new SubjectId( id ),
		label,
		schemaId,
		properties,
		pageIdentifiers
	);
}
