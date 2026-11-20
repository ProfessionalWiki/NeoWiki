<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Closure;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectIndexer;
use Wikimedia\Rdbms\IDBAccessObject;

class PageRebuilder {

	/**
	 * @param Closure(PageId): void $queueProjection Queues the page's graph projection for once the caller's
	 *        transaction has committed
	 */
	public function __construct(
		private readonly SubjectIndexer $indexer,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly Closure $queueProjection,
	) {
	}

	/**
	 * Indexes the page's current revision at once and queues its projection, for a change that writes no
	 * revision: an approval extension's answer changing, an import, an undeletion or a revision-visibility
	 * change. Read from the primary: a replica can still hold the revision an edit or import just replaced, and
	 * the indexes would be written from it.
	 *
	 * @return bool Whether the page has a current revision to refresh from
	 */
	public function rebuild( Title $title ): bool {
		$page = $this->wikiPageFactory->newFromTitle( $title );
		$page->loadPageData( IDBAccessObject::READ_LATEST );
		$revision = $page->getRevisionRecord();

		if ( $revision === null ) {
			return false;
		}

		$this->indexer->indexRevision( $revision );
		( $this->queueProjection )( new PageId( $page->getId() ) );

		return true;
	}

}
