<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use Wikimedia\Rdbms\IDBAccessObject;

class SubjectPageRebuilder {

	public function __construct(
		private readonly OnRevisionCreatedHandler $handler,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
	}

	public function rebuild( Title $title ): PageRefreshOutcome {
		return $this->rebuildWithReadFlags( $title, IDBAccessObject::READ_NORMAL );
	}

	/**
	 * Rebuilds from the primary database. Needed when rebuilding right after a write, such as on the
	 * import path: a replica can still be missing the page, or still carry the revision the import
	 * replaced, which would project outdated content.
	 */
	public function rebuildFromPrimary( Title $title ): PageRefreshOutcome {
		return $this->rebuildWithReadFlags( $title, IDBAccessObject::READ_LATEST );
	}

	private function rebuildWithReadFlags( Title $title, int $readFlags ): PageRefreshOutcome {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$wikiPage->loadPageData( $readFlags );

		$revision = $wikiPage->getRevisionRecord();

		if ( $revision === null ) {
			return PageRefreshOutcome::SkippedMissingRevision;
		}

		return $this->handler->onRevisionCreated( $revision, $revision->getUser() )
			? PageRefreshOutcome::Refreshed
			: PageRefreshOutcome::SkippedMissingSubjectSlot;
	}

}
