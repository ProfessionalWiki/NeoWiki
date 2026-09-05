import type { Subject } from '@/domain/Subject';
import { placeholderSubjectLabel } from '@/domain/placeholderSubjectLabel';

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
 * surface will show once it does: a page that already has a Main Subject gives this one its Schema
 * name, which is the tier nobody chose.
 */
export function newSubjectNamePreview( pageHasMainSubject: boolean, pageName: string, schemaName: string ): string {
	const name = placeholderSubjectLabel( pageHasMainSubject, pageName, schemaName );

	return pageHasMainSubject ? generatedName( name ) : name;
}
