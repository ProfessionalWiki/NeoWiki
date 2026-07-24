import { SubjectId } from '@/domain/SubjectId';

// The DOM id of a Subject's row on the Data tab (?action=subjects). Internal rendering detail — HTML
// hygiene and in-page scroll targeting — not a public contract. A row's public deep-link anchor is the
// bare Subject id in the URL fragment (see subjectIdFromHash), which the mount handler resolves to a row
// itself, so the fragment and this DOM id are decoupled.
const ROW_ID_PREFIX = 'ext-neowiki-subject-row-';

export function subjectRowDomId( subjectId: string ): string {
	return ROW_ID_PREFIX + subjectId;
}

// The Subject id encoded in a row DOM id, or null when the string is not one of ours. Reads the Subject
// off a dragged row element, whose id subjectRowDomId built.
export function subjectIdFromRowDomId( domId: string ): string | null {
	if ( !domId.startsWith( ROW_ID_PREFIX ) ) {
		return null;
	}

	const subjectId = domId.slice( ROW_ID_PREFIX.length );

	return SubjectId.isValid( subjectId ) ? subjectId : null;
}

// The Subject id carried by a Data tab URL fragment, or null when the fragment is not a Subject id (an
// unrelated anchor, or junk — either leaves the Data tab untouched). The dereference endpoint's data-tab
// Location and manual row expansion both write a bare Subject id as the fragment, like Wikibase's
// `#P123`; this reads it back. Deliberately the Subject id, not the row DOM id.
export function subjectIdFromHash( hash: string ): string | null {
	return SubjectId.isValid( hash ) ? hash : null;
}
