<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki;

use CommentStoreComment;
use Content;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Storage\PageUpdater;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use WikiPage;

class PageContentSaver {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly Authority $performer,
	) {
	}

	/**
	 * @param array<string, Content> $contentBySlot Keys are slot names, values are Content objects
	 */
	public function saveContent( PageIdentity|PageId $page, array $contentBySlot, CommentStoreComment $comment ): PageContentSavingStatus {
		$wikiPage = $this->wikiPageFromPageId( $page );

		if ( $wikiPage === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'Page not found' );
		}

		$updater = $wikiPage->newPageUpdater( $this->performer );

		$this->saveContentViaUpdater( $updater, $contentBySlot, $comment );

		return $this->buildStatusFromUpdater( $updater );
	}

	/**
	 * @param array<string, Content> $contentBySlot
	 */
	private function saveContentViaUpdater( PageUpdater $updater, array $contentBySlot, CommentStoreComment $comment ): void {
		foreach ( $contentBySlot as $slotName => $content ) {
			$updater->setContent( $slotName, $content );
		}

		$updater->saveRevision( $comment );
	}

	private function buildStatusFromUpdater( PageUpdater $updater ): PageContentSavingStatus {
		if ( $updater->wasSuccessful() ) {
			if ( $updater->wasRevisionCreated() ) {
				return new PageContentSavingStatus( PageContentSavingStatus::REVISION_CREATED );
			}

			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		return new PageContentSavingStatus(
			PageContentSavingStatus::ERROR,
			$updater->getStatus()?->getWikiText() ?? 'Unknown error'
		);
	}

	private function wikiPageFromPageId( PageIdentity|PageId $page ): ?WikiPage {
		if ( $page instanceof PageId ) {
			return $this->wikiPageFactory->newFromId( $page->id );
		}

		return $this->wikiPageFactory->newFromTitle( $page );
	}

}
