<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Persistence\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use WikiPage;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly GraphDatabasePlugin $graphDatabasePlugin,
		private readonly PagePropertiesBuilder $pagePropertiesBuilder,
	) {
	}

	public function onRevisionCreated( RevisionRecord $revisionRecord, UserIdentity $user ): bool {
		return $this->storeRevisionRecord( $revisionRecord, $user );
	}

	private function storeRevisionRecord( RevisionRecord $revisionRecord, ?UserIdentity $user ): bool {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new \RuntimeException( 'Page ID should not be 0' );
		}

		$neoContent = $this->getNeoContent( $revisionRecord );

		if ( $neoContent === null ) {
			return false;
		}

		$contentData = $neoContent->getPageSubjects();

		$this->graphDatabasePlugin->savePage(
			new Page(
				id: new PageId( $revisionRecord->getPageId() ),
				properties: $this->pagePropertiesBuilder->getPagePropertiesFor( $revisionRecord, $user ),
				subjects: new PageSubjects(
					mainSubject: $contentData->getMainSubject(),
					childSubjects: $contentData->getChildSubjects()
				)
			)
		);

		return true;
	}

	private function getNeoContent( RevisionRecord $revisionRecord ): ?SubjectContent {
		try {
			$content = $revisionRecord->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );
		}
		catch ( RevisionAccessException ) {
			// TODO: log this
			return null;
		}

		if ( $content instanceof SubjectContent ) {
			return $content;
		}

		// TODO: log this
		return null;
	}

	public function onPageDelete( int $pageId ): void {
		$this->graphDatabasePlugin->deletePage( new PageId( $pageId ) );
	}

	public function onPageUndelete( RevisionRecord $restoredRevision ): void {
		// Calling isCurrent() on the RevisionRecord does not work, because it is always false.
		$this->storeRevisionRecord( $restoredRevision, null );
	}

}
