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
		private readonly RevisionPolicy $revisionPolicy,
	) {
	}

	/**
	 * Reprojects the page from the revision the registered policy publishes. This is the path an
	 * approval extension calls when its answer changes. A page save takes the other path: it already
	 * knows its revision and is only asked whether to publish it.
	 *
	 * The handler this hands the substituted revision to must not index, since it would index the
	 * published revision's Subjects in place of the latest one's. See NeoWikiExtension.
	 */
	public function rebuild( Title $title ): PageRefreshOutcome {
		return $this->rebuildWithReadFlags( $title, IDBAccessObject::READ_NORMAL, substitute: true );
	}

	/**
	 * Rebuilds from the primary database. Needed when rebuilding right after a write, such as on the
	 * import path: a replica can still be missing the page, or still carry the revision the import
	 * replaced, which would project outdated content.
	 */
	public function rebuildFromPrimary( Title $title ): PageRefreshOutcome {
		return $this->rebuildWithReadFlags( $title, IDBAccessObject::READ_LATEST, substitute: false );
	}

	private function rebuildWithReadFlags( Title $title, int $readFlags, bool $substitute ): PageRefreshOutcome {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$wikiPage->loadPageData( $readFlags );

		$revision = $wikiPage->getRevisionRecord();

		if ( $revision === null ) {
			return PageRefreshOutcome::SkippedMissingRevision;
		}

		if ( $substitute ) {
			$published = $this->revisionPolicy->publishedRevision( $revision );

			if ( $published === null ) {
				return PageRefreshOutcome::SkippedUnpublishableRevision;
			}

			$revision = $published;
		}

		return $this->handler->onRevisionCreated( $revision );
	}

}
