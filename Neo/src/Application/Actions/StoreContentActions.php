<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions;

use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\PageInfo;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;

class StoreContentActions {

	public function __construct(
		private readonly QueryStore $queryStore,
	) {
	}

	public function onPageSave( RenderedRevision $renderedRevision ): void {
		$this->storeRevisionRecord( $renderedRevision->getRevision() );
	}

	private function storeRevisionRecord( RevisionRecord $revisionRecord ): void {
		$allSubjects = new SubjectMap();

		foreach ( $revisionRecord->getSlots()->getSlots() as $slot ) {
			$content = $slot->getContent();

			if ( $content instanceof SubjectContent ) {
				$allSubjects->append( $content->getSubjects() );
			}
		}

		$this->queryStore->savePage(
			pageId: $revisionRecord->getPageId(),
			pageInfo: new PageInfo(
				title: $revisionRecord->getPageAsLinkTarget()->getText()
			),
			subjects: $allSubjects
		);
	}

	public function onPageDelete( int $pageId ): void {
		$this->queryStore->deletePage( $pageId );
	}

	public function onPageUndelete( RevisionRecord $restoredRevision ): void {
		// TODO: this might be getting called for all revisions. We should only store the latest one.
		// Calling isCurrent() on the RevisionRecord does not work, because it is always false.
		$this->storeRevisionRecord( $restoredRevision );
	}

}
