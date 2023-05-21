import { Subject } from '@/editor/domain/Subject';
import type { SubjectProperties } from '@/editor/domain/Subject';
import { SubjectId } from '@/editor/domain/SubjectId';

export const ZERO_GUID = '00000000-0000-0000-0000-000000000000';
export const DEFAULT_TEST_SUBJECT_LABEL = 'Test subject';
export const DEFAULT_TEST_SCHEMA_ID = 'TestSchema';

export function newTestSubject(
	id: string|SubjectId = ZERO_GUID,
	label: string = DEFAULT_TEST_SUBJECT_LABEL,
	schemaId: string = DEFAULT_TEST_SCHEMA_ID,
	properties: SubjectProperties = {}
): Subject {
	return new Subject(
		id instanceof SubjectId ? id : new SubjectId( id ),
		label,
		schemaId,
		properties
	);
}
