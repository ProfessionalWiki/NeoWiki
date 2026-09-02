import type { Subject } from '@/domain/Subject';

/**
 * The placeholder a rename field shows for an existing Subject. One with no label of its own is
 * already displayed under a name the server derived (ADR 31), so clearing the field leaves exactly
 * that name behind and the field can preview it; a labelled Subject has no such name to preview,
 * so the field names itself instead.
 */
export function subjectLabelPlaceholder( subject: Subject ): string {
	return subject.getLabel() === null ?
		subject.getDisplayName() :
		mw.msg( 'neowiki-subject-editor-label-field' );
}
