import type { Subject } from '@/domain/Subject';

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
 * The name to show for a Subject being edited that has no stored label: the label its Schema's label
 * template reads from the form, else the name the server derived. That name came from the template too
 * when the saved values gave it something to read, so once the form's values leave it nothing, it is
 * stale and the Schema stands in, as for any Subject nobody named. The server may give a Main Subject its
 * page name instead, which the form cannot tell.
 */
export function editedSubjectDisplayName(
	subject: Subject,
	formTemplateLabel: string | null,
	savedTemplateLabel: string | null,
): string {
	if ( formTemplateLabel !== null ) {
		return formTemplateLabel;
	}

	return savedTemplateLabel === null ? subjectDisplayName( subject ) : generatedName( subject.getSchemaName() );
}

/**
 * A Schema name presented as the stand-in it is. The one place the marker's shape is written.
 */
function generatedName( schemaName: string ): string {
	return mw.msg( 'neowiki-subject-generated-name', schemaName );
}
