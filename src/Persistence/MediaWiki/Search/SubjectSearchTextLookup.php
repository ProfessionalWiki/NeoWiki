<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search;

use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchTextBuilder;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectSlotReader;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * The search text of a page's Subjects, following the latest revision as MediaWiki's own index does.
 */
readonly class SubjectSearchTextLookup {

	public function __construct(
		private RevisionLookup $revisionLookup,
		private SubjectSearchTextBuilder $textBuilder,
	) {
	}

	public function getSearchTextForPage( int $pageId ): string {
		// From the primary database: indexing is deferred from the edit, which a replica may not have yet.
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId, 0, IDBAccessObject::READ_LATEST );

		return $revision === null ? '' : $this->getSearchTextForRevision( $revision );
	}

	public function getSearchTextForRevision( RevisionRecord $revision ): string {
		$content = SubjectSlotReader::read( $revision );

		return $content === null ? '' : $this->textBuilder->build( $content->getPageSubjects() );
	}

}
