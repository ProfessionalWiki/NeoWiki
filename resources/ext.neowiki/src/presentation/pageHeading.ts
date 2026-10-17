import type { Subject } from '@/domain/Subject';
import { subjectDisplayName } from '@/presentation/subjectDisplayName';

/**
 * Where the page heading shows a Subject's label, marked by SubjectLabelHtml, shows the Subject's current
 * one there and in the browser tab. A heading that marks none, such as a title someone chose, stays.
 */
export function showLabelInPageHeading( subject: Subject ): void {
	const label = document.querySelector( '#firstHeading .ext-neowiki-subject-label' );

	if ( label === null ) {
		return;
	}

	label.replaceChildren( labelElement( subject ) );
	document.title = mw.msg( 'pagetitle', subjectDisplayName( subject ) );
}

function labelElement( subject: Subject ): HTMLElement {
	if ( subject.hasGeneratedDisplayName() ) {
		const noLabel = document.createElement( 'span' );
		noLabel.className = 'ext-neowiki-subject-label-none';
		noLabel.textContent = mw.msg( 'neowiki-subject-no-label' );

		return noLabel;
	}

	const label = document.createElement( 'bdi' );
	label.textContent = subject.getDisplayName();

	return label;
}
