import type { Subject } from '@/domain/Subject';
import type { SubjectId } from '@/domain/SubjectId';

/**
 * The relation targets of these Subjects that a response holds no Subject for. Judged by the ids the
 * response holds rather than by the Subjects the client could deserialize, so a target it fails to
 * read is not taken for one that was not found.
 */
export function notFoundTargetIds( subjects: Subject[], returnedIds: Iterable<string> ): SubjectId[] {
	const returned = new Set( returnedIds );

	return subjects
		.flatMap( ( subject ) => [ ...subject.getStatements().getIdsOfReferencedSubjects() ] )
		.filter( ( target ) => !returned.has( target.text ) );
}
