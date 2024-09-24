<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints;

use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\MediaWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use WikiPage;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly QueryStore $queryStore,
		private readonly PagePropertiesBuilder $pagePropertiesBuilder,
	) {
	}

	public function onRevisionCreated( RevisionRecord $revisionRecord, WikiPage $wikiPage, UserIdentity $user ): void {
		$this->storeRevisionRecord( $revisionRecord, $wikiPage, $user );
	}

	private function storeRevisionRecord( RevisionRecord $revisionRecord, ?WikiPage $wikiPage, ?UserIdentity $user ): void {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new \RuntimeException( 'Page ID should not be 0' );
		}

		$neoContent = $this->getNeoContent( $revisionRecord );

		if ( $neoContent === null ) {
			return;
		}

		$contentData = $neoContent->getPageSubjects();

		$this->queryStore->savePage(
			new Page(
				id: new PageId( $revisionRecord->getPageId() ),
				properties: $this->pagePropertiesBuilder->getPagePropertiesFor( $revisionRecord, $wikiPage, $user ),
				subjects: new PageSubjects(
					mainSubject: $contentData->getMainSubject(),
					childSubjects: $contentData->getChildSubjects()
				)
			)
		);
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
		$this->queryStore->deletePage( new PageId( $pageId ) );
	}

	public function onPageUndelete( RevisionRecord $restoredRevision ): void {
		// Calling isCurrent() on the RevisionRecord does not work, because it is always false.
		$this->storeRevisionRecord( $restoredRevision, null, null );
	}

}
