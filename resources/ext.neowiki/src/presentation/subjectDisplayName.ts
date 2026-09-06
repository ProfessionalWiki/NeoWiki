import type { SubjectWithContext } from '@/domain/SubjectWithContext';
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
 */
export function subjectDisplayName( subject: SubjectWithContext ): string {
	return subject.getChosenName() ?? generatedName( subject.getSchemaName() );
}

/**
 * The same name without the marker, for the relation picker: its selection becomes the value of a
 * text input the user can then edit and submit.
 */
export function unmarkedSubjectName( subject: SubjectWithContext ): string {
	return subject.getChosenName() ?? subject.getSchemaName();
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
	return chosenSubjectName( null, !pageHasMainSubject, pageName ) ?? generatedName( schemaName );
}
