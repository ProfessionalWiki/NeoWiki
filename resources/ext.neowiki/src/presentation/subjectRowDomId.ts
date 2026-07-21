import { SubjectId } from '@/domain/SubjectId';

// The DOM id — and, identically, the URL fragment — of a Subject's row on the Data tab
// (?action=subjects). The subject-IRI dereference endpoint mints `#<domId>` into its Location when the
// wiki targets the Data tab, and the Data tab reads the fragment back to expand, scroll to, and
// highlight the row.
//
// Single source of truth for this scheme on the TypeScript side. Its PHP counterpart is SubjectRowAnchor
// in src/Presentation/SubjectRowAnchor.php; the two must stay in lockstep. Each side has a test asserting
// the same literal example, so a prefix change on one side alone breaks a test.
const ROW_ID_PREFIX = 'ext-neowiki-subject-row-';

export function subjectRowDomId( subjectId: string ): string {
	return ROW_ID_PREFIX + subjectId;
}

// The Subject id carried by a row DOM id (or the equivalent URL fragment), or null when the string is
// not one of ours: it lacks the prefix, or the part after the prefix is not a valid Subject id. Callers
// treat null as "nothing to do" (e.g. an unrelated fragment leaves the Data tab untouched).
export function subjectIdFromRowDomId( domId: string ): string | null {
	if ( !domId.startsWith( ROW_ID_PREFIX ) ) {
		return null;
	}

	const subjectId = domId.slice( ROW_ID_PREFIX.length );

	return SubjectId.isValid( subjectId ) ? subjectId : null;
}
