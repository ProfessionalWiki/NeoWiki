<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Authorizes or denies every page read.
 */
class StubPageReadAuthorizer implements PageReadAuthorizer {

	public function __construct(
		private bool $allowed
	) {
	}

	public function authorizeReadByPageId( PageId $pageId ): bool {
		return $this->allowed;
	}

	public function authorizeReadByPageTitle( Title $title ): bool {
		return $this->allowed;
	}

}
