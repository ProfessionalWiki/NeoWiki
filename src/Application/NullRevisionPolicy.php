<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class NullRevisionPolicy implements RevisionPolicy {

	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
		return $revision;
	}

	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
		return true;
	}

}
