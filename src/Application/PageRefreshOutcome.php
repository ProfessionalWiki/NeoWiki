<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

/**
 * The outcome of refreshing a page's graph data without an edit
 * (see SubjectPageRebuilder::rebuild()).
 */
enum PageRefreshOutcome: string {
	case Refreshed = 'refreshed';
	case SkippedMissingRevision = 'skippedMissingRevision';
	case SkippedMissingSubjectSlot = 'skippedMissingSubjectSlot';
}
