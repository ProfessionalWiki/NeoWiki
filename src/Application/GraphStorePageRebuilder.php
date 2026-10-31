<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphStoreProjector;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Projects pages into the one graph store a rebuild is scoped to, leaving the indexes alone: the rebuild walks
 * the wiki from a replica, so the revision it projects may already have been superseded.
 */
class GraphStorePageRebuilder {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly GraphStoreProjector $projector,
	) {
	}

	public function rebuildFromReplica( Title $title ): PageRefreshOutcome {
		$page = $this->wikiPageFactory->newFromTitle( $title );
		$page->loadPageData( IDBAccessObject::READ_NORMAL );
		$revision = $page->getRevisionRecord();

		if ( $revision === null ) {
			return PageRefreshOutcome::SkippedMissingRevision;
		}

		return $this->projector->projectRevision( $revision, null );
	}

}
