import type { Subject } from '@/domain/Subject';

/**
 * The name to show for a Subject.
 *
 * A Subject nobody named is shown under its id, in brackets that say the string is a stand-in the
 * system supplied — the convention MediaWiki states in the documentation of `blanknamespace`,
 * "(Main)": "Surrounded by brackets to signal that it's only a symbolic label and not an actual
 * namespace prefix." They also set it apart from the bare id a relation shows for a target that
 * cannot be found.
 *
 * It has to be in the string rather than in styling: several surfaces interpolate a display name
 * into plain text no CSS reaches, and colour alone would carry the meaning nowhere for anyone using
 * a screen reader.
 *
 * Not for the relation picker, whose selection becomes the value of a text input the user can then
 * edit and submit.
 */
export function subjectDisplayName( subject: Subject ): string {
	return subject.hasGeneratedDisplayName() ? generatedSubjectName( subject.getId().text ) : subject.getDisplayName();
}

/**
 * A Subject id presented as the stand-in it is. The frontend's one place that composes the marker;
 * the backend's is SubjectNameMessage.
 */
export function generatedSubjectName( subjectId: string ): string {
	return mw.msg( 'neowiki-subject-generated-name', subjectId );
}
