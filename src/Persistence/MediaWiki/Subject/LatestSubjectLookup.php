<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;

/**
 * The Subjects as an editor sees them: every hosting page at its latest revision. A page whose latest
 * revision the revision policy refuses the viewer contributes no Subjects, so a refusal reads exactly
 * like a page without any.
 */
readonly class LatestSubjectLookup extends PageRevisionSubjectLookup {

	public function __construct(
		PageIdentifiersLookup $pageIdentifiersLookup,
		RevisionLookup $revisionLookup,
		private RevisionPolicy $revisionPolicy,
		private Authority $viewer,
	) {
		parent::__construct( $pageIdentifiersLookup, $revisionLookup );
	}

	protected function revisionToRead( RevisionRecord $latestRevision ): ?RevisionRecord {
		return $this->revisionPolicy->revisionIsReadableBy( $latestRevision, $this->viewer ) ? $latestRevision : null;
	}

}
