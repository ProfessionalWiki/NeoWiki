<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

/**
 * A saver that persists nothing and reports the outcome the test asked for, so the import action can
 * be exercised without touching the database.
 */
class PageContentSaverStub extends PageContentSaver {

	/**
	 * @param string[] $failingKeys DB keys whose save is rejected.
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		Authority $performer,
		private readonly array $failingKeys = []
	) {
		parent::__construct( $wikiPageFactory, $performer );
	}

	public function saveContent( PageIdentity|PageId $page, array $contentBySlot, CommentStoreComment $comment ): PageContentSavingStatus {
		if ( $page instanceof PageIdentity && in_array( $page->getDBkey(), $this->failingKeys, true ) ) {
			return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'forced failure' );
		}

		return new PageContentSavingStatus( PageContentSavingStatus::REVISION_CREATED );
	}

}
