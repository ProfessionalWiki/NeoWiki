import { Subject } from '@/editor/domain/Subject';
import type { SubjectProperties } from '@/editor/domain/Subject';
import { SubjectId } from '@/editor/domain/SubjectId';

export const ZERO_GUID = '00000000-0000-0000-0000-000000000000';

export function newTestSubject(
	id: string|SubjectId|null = null,
	label: string|null = null,
	schemaId: string|null = null,
	properties: SubjectProperties = {}
): Subject {
	return new Subject(
		id instanceof SubjectId ? id : new SubjectId( id || ZERO_GUID ),
		label || 'Test subject',
		schemaId || 'TestSchema',
		properties
	);
}
