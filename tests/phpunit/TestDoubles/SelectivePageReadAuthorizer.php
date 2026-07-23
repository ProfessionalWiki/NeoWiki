<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Authorizes every page read except for a fixed set of denied page ids. Lets a list-filter test
 * hide specific pages without touching the MediaWiki database, so the caller's own filtering and
 * over-fetch logic can be exercised in isolation.
 */
class SelectivePageReadAuthorizer implements PageReadAuthorizer {

	/**
	 * @param int[] $deniedPageIds
	 */
	public function __construct(
		private array $deniedPageIds
	) {
	}

	public function authorizeReadByPageId( PageId $pageId ): bool {
		return !in_array( $pageId->id, $this->deniedPageIds, true );
	}

	public function authorizeReadByPageTitle( Title $title ): bool {
		return !in_array( $title->getId(), $this->deniedPageIds, true );
	}

}
