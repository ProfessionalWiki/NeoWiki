<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
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
		return $this->save( $page, $contentBySlot, $comment, 0 );
	}

	/**
	 * The same, for a write that must make the page rather than edit it: EDIT_NEW has MediaWiki
	 * refuse a page that exists. A check before the write cannot stand in for that, since whether
	 * the page exists is read from a replica.
	 *
	 * @param array<string, Content> $contentBySlot Keys are slot names, values are Content objects
	 */
	public function createPage( PageIdentity $page, array $contentBySlot, CommentStoreComment $comment ): PageContentSavingStatus {
		return $this->save( $page, $contentBySlot, $comment, EDIT_NEW );
	}

	/**
	 * @param array<string, Content> $contentBySlot
	 */
	private function save(
		PageIdentity|PageId $page,
		array $contentBySlot,
		CommentStoreComment $comment,
		int $flags
	): PageContentSavingStatus {
		$wikiPage = $this->wikiPageFromPageId( $page );

		if ( $wikiPage === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'Page not found' );
		}

		$updater = $wikiPage->newPageUpdater( $this->performer );

		$this->saveContentViaUpdater( $updater, $contentBySlot, $comment, $flags );

		return $this->buildStatusFromUpdater( $updater );
	}

	/**
	 * @param array<string, Content> $contentBySlot
	 */
	private function saveContentViaUpdater(
		PageUpdater $updater,
		array $contentBySlot,
		CommentStoreComment $comment,
		int $flags
	): void {
		foreach ( $contentBySlot as $slotName => $content ) {
			$updater->setContent( $slotName, $content );
		}

		$updater->saveRevision( $comment, $flags );
	}

	private function buildStatusFromUpdater( PageUpdater $updater ): PageContentSavingStatus {
		if ( $updater->wasSuccessful() ) {
			// A successful save that created no revision is a null edit, which PageUpdater reports
			// both by wasRevisionCreated() and by having no new revision to hand back.
			$newRevision = $updater->getNewRevision();

			if ( $newRevision !== null ) {
				return new PageContentSavingStatus(
					PageContentSavingStatus::REVISION_CREATED,
					pageId: new PageId( $newRevision->getPageId() )
				);
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
