/**
 * The name someone chose for a Subject: its stored label, else the name of its page when it is that
 * page's Main Subject and so represents the page's own topic. Null when nobody named it; every surface
 * then falls back to the Schema name, which the UI marks as a stand-in (ADR 31).
 *
 * Mirrors SubjectDisplayName::labelOrPageName() in the PHP backend. Change one and change the other.
 */
export function chosenSubjectName( label: string | null, isMainSubject: boolean, pageName: string ): string | null {
	if ( label !== null ) {
		return label;
	}

	if ( isMainSubject && pageName !== '' ) {
		return pageName;
	}

	return null;
}
