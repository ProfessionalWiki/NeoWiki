<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

/**
 * Where the subject-IRI dereference endpoint sends a browser (an HTML `Accept`): the Subject's hosting
 * page, or that page's Data tab (`?action=subjects`) opened on the Subject's row. Set per wiki via
 * `$wgNeoWikiSubjectDereferenceTarget`.
 */
enum SubjectDereferenceTarget: string {

	case Page = 'page';
	case DataTab = 'data-tab';

}
