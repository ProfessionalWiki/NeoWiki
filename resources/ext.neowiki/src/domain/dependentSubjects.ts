import type { Schema } from '@/domain/Schema';
import type { Subject } from '@/domain/Subject';

/**
 * A Subject whose Schema is not among these counts as standalone.
 */
export function isDependentSubject(
	subject: Subject,
	schemas: ReadonlyMap<string, Pick<Schema, 'subjectsAreDependent'>>,
): boolean {
	return schemas.get( subject.getSchemaName() )?.subjectsAreDependent() === true;
}

/**
 * A read names a Dependent Subject after its Host Subject, which tells one where its Schema is not at hand. False for a
 * Dependent Subject that has a label, or that nothing points at.
 */
export function isNamedAfterHostSubject( subject: Subject ): boolean {
	return subject.getHostSubject() !== null;
}
