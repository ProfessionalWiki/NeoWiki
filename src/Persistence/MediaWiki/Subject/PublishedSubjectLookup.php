<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;

/**
 * The Subjects as the wiki publishes them: every hosting page is read at the revision its
 * RevisionPolicy publishes, which on a wiki with an approval extension is not the latest one. A page
 * that publishes nothing contributes no Subjects, so a Subject held only by a draft is not found.
 */
readonly class PublishedSubjectLookup extends PageRevisionSubjectLookup {

	public function __construct(
		PageIdentifiersLookup $pageIdentifiersLookup,
		RevisionLookup $revisionLookup,
		private RevisionPolicy $revisionPolicy,
	) {
		parent::__construct( $pageIdentifiersLookup, $revisionLookup );
	}

	protected function revisionToRead( RevisionRecord $latestRevision ): ?RevisionRecord {
		return $this->revisionPolicy->publishedRevision( $latestRevision );
	}

}
