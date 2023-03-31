<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly QueryStore $queryStore,
	) {
	}

	public function onRevisionCreated( RevisionRecord $revisionRecord ): void {
		$this->storeRevisionRecord( $revisionRecord );
	}

	private function storeRevisionRecord( RevisionRecord $revisionRecord ): void {
		$allSubjects = new SubjectMap();

		foreach ( $revisionRecord->getSlots()->getSlots() as $slot ) {
			$content = $slot->getContent();

			if ( $content instanceof SubjectContent ) {
				$allSubjects->append( $content->getSubjects() );
			}
		}

		if ( $revisionRecord->getPageId() === 0 ) {
			throw new \RuntimeException( 'Page ID should not be 0' );
		}

		$this->queryStore->savePage(
			new Page(
				id: new PageId( $revisionRecord->getPageId() ),
				properties: new PageProperties(
					title: $revisionRecord->getPageAsLinkTarget()->getText()
				),
				mainSubject: null, // TODO
				childSubjects: $allSubjects
			)
		);
	}

	public function onPageDelete( int $pageId ): void {
		$this->queryStore->deletePage( new PageId( $pageId ) );
	}

	public function onPageUndelete( RevisionRecord $restoredRevision ): void {
		// TODO: is this needed? Likely onRevisionCreated is already triggered

		// TODO: this might be getting called for all revisions. We should only store the latest one.
		// Calling isCurrent() on the RevisionRecord does not work, because it is always false.
		$this->storeRevisionRecord( $restoredRevision );
	}

}
