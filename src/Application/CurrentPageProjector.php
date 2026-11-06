<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RenderedRevision;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphStoreProjector;
use Wikimedia\Rdbms\IDBAccessObject;

class CurrentPageProjector {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly GraphStoreProjector $projector,
		private readonly PageProjectionLock $projectionLock,
		private readonly PageRenderings $renderings,
	) {
	}

	/**
	 * Projects the page's current revision into the graph stores, or withdraws the page once it no longer
	 * exists.
	 *
	 * @param RenderedRevision|null $rendering The rendering MediaWiki made of a revision of the page before
	 *        this projection, when it made one
	 *
	 * @throws PageProjectionLockUnavailableException
	 */
	public function project( PageId $pageId, ?RenderedRevision $rendering ): void {
		$this->projectionLock->whileHeld(
			$pageId,
			fn () => $this->projectAsItStands( $pageId, $rendering )
		);
	}

	private function projectAsItStands( PageId $pageId, ?RenderedRevision $rendering ): void {
		$revision = $this->wikiPageFactory->newFromID( $pageId->id, IDBAccessObject::READ_LATEST )?->getRevisionRecord();

		if ( $revision === null ) {
			$this->projector->withdrawPage( $pageId );
			return;
		}

		$this->projector->projectRevision( $revision, $rendering );

		$this->renderings->expireAfterProjection(
			$pageId,
			$rendering?->getRevisionParserOutput( [ 'generate-html' => false ] )
		);
	}

}
