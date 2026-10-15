<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use Wikimedia\Rdbms\IDBAccessObject;

class PageRebuilder {

	public function __construct(
		private readonly OnRevisionCreatedHandler $handler,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
	}

	/**
	 * Reprojects the page from its current revision, which is what an approval extension calls when its
	 * answer changes. What that revision publishes is the handler's decision. Read from the primary: a
	 * replica can still hold the revision an edit or import just replaced, and the published Subject index
	 * would be written from it.
	 */
	public function rebuild( Title $title ): PageRefreshOutcome {
		return $this->rebuildWithReadFlags( $title, IDBAccessObject::READ_LATEST );
	}

	/**
	 * As rebuild(), from a replica, for a walk over every page such as a graph rebuild.
	 */
	public function rebuildFromReplica( Title $title ): PageRefreshOutcome {
		return $this->rebuildWithReadFlags( $title, IDBAccessObject::READ_NORMAL );
	}

	private function rebuildWithReadFlags( Title $title, int $readFlags ): PageRefreshOutcome {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$wikiPage->loadPageData( $readFlags );

		$revision = $wikiPage->getRevisionRecord();

		if ( $revision === null ) {
			return PageRefreshOutcome::SkippedMissingRevision;
		}

		return $this->handler->onRevisionCreated( $revision );
	}

}
