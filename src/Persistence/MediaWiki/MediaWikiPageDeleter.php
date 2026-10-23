<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Exception;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Persistence\PageDeleter;
use ProfessionalWiki\NeoWiki\Persistence\PageDeletionStatus;

class MediaWikiPageDeleter implements PageDeleter {

	public function __construct(
		private readonly DeletePageFactory $deletePageFactory,
		private readonly Authority $performer,
	) {
	}

	/**
	 * Deletes immediately, also a page with more revisions than MediaWiki otherwise deletes at once, which it
	 * would leave to the job queue.
	 */
	public function deletePage( ProperPageIdentity $page, string $reason ): PageDeletionStatus {
		try {
			$status = $this->deletePageFactory
				->newDeletePage( $page, $this->performer )
				->forceImmediate( true )
				->deleteUnsafe( $reason );
		} catch ( Exception $e ) {
			return new PageDeletionStatus( false, $e->getMessage() );
		}

		if ( $status->isGood() ) {
			return new PageDeletionStatus( true );
		}

		return new PageDeletionStatus( false, $status->getWikiText() );
	}

}
