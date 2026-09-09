<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

/**
 * Publishes every revision, keeps whichever one the caller resolved, and hides none. This is what runs
 * when no extension registers a policy, so an installation without an approval extension behaves
 * exactly as it did before there was a policy at all.
 */
class NullRevisionPolicy implements RevisionPolicy {

	public function publishesRevision( RevisionRecord $revision ): bool {
		return true;
	}

	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
		return $revision;
	}

	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
		return true;
	}

}
