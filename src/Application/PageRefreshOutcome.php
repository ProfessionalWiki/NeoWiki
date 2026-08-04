<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use LogicException;

/**
 * The outcome of projecting a page's graph data, whether from a new revision or from a refresh
 * without an edit (see PageRebuilder::rebuild()).
 */
enum PageRefreshOutcome: string {
	case Refreshed = 'refreshed';
	case SkippedMissingRevision = 'skippedMissingRevision';
	case SkippedUnreadableSubjects = 'skippedUnreadableSubjects';
	case SkippedUnreadablePageProperties = 'skippedUnreadablePageProperties';

	/**
	 * Why the page was not written, phrased to complete "Skipped <page>: ...".
	 *
	 * @throws LogicException when the page was written after all.
	 */
	public function skipReason(): string {
		return match ( $this ) {
			self::SkippedMissingRevision => 'no current revision',
			self::SkippedUnreadableSubjects => 'its subject slot does not hold Subject data',
			self::SkippedUnreadablePageProperties => 'its page properties could not be built',
			self::Refreshed => throw new LogicException( 'Refreshed is not a skip' ),
		};
	}
}
