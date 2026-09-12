import type { Subject } from '@/domain/Subject';
import { chosenSubjectName } from '@/domain/chosenSubjectName';

/**
 * The name to show for a Subject.
 *
 * A Subject nobody named is shown under its Schema name, which is indistinguishable from a label
 * someone chose to type. Brackets say the string is a stand-in the system supplied — the convention
 * MediaWiki states in the documentation of `blanknamespace`, "(Main)": "Surrounded by brackets to
 * signal that it's only a symbolic label and not an actual namespace prefix."
 *
 * It has to be in the string rather than in styling: several surfaces interpolate a display name
 * into plain text no CSS reaches, and colour alone would carry the meaning nowhere for anyone using
 * a screen reader.
 *
 * Not for the relation picker, whose selection becomes the value of a text input the user can then
 * edit and submit.
 */
export function subjectDisplayName( subject: Subject ): string {
	return subject.hasGeneratedDisplayName() ? generatedName( subject.getDisplayName() ) : subject.getDisplayName();
}

/**
 * A Schema name presented as the stand-in it is. The one place the marker's shape is written.
 */
function generatedName( schemaName: string ): string {
	return mw.msg( 'neowiki-subject-generated-name', schemaName );
}

/**
 * The name the subject creator previews for a Subject that does not exist yet, matching what every
 * surface will show once it does: a page that already has a Main Subject, and a page with no name
 * of its own, both give this one its Schema name, which is the tier nobody chose. A null page name
 * is a page that has yet to be titled; pageSubjectIds are the ids of the Subjects the page holds,
 * one of which may already have titled it.
 */
export function newSubjectNamePreview(
	pageHasMainSubject: boolean,
	pageName: string | null,
	pageSubjectIds: string[],
	schemaName: string,
): string {
	return chosenSubjectName( pageHasMainSubject, pageName, pageSubjectIds ) ?? generatedName( schemaName );
}
