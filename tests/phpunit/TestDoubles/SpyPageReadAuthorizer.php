<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Authorizes or denies every page read, recording the PageIds it was asked about, so a test can pin
 * that a gate asks about the page it resolved rather than some other one.
 */
class SpyPageReadAuthorizer implements PageReadAuthorizer {

	public ?PageId $authorizedPageId = null;

	public function __construct(
		private bool $allowed
	) {
	}

	public function authorizeReadByPageId( PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;

		return $this->allowed;
	}

	public function authorizeReadByPageTitle( Title $title ): bool {
		return $this->allowed;
	}

}
