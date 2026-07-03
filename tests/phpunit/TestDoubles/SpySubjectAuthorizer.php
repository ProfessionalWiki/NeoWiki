<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Configurable per-method authorizer that records the last PageId it was asked about.
 */
class SpySubjectAuthorizer implements SubjectAuthorizer {

	public ?PageId $authorizedPageId = null;

	public function __construct(
		private bool $mainAllowed = true,
		private bool $childAllowed = true,
		private bool $editAllowed = true,
		private bool $deleteAllowed = true,
	) {
	}

	public function canCreateMainSubject( ?PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;
		return $this->mainAllowed;
	}

	public function canCreateChildSubject( ?PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;
		return $this->childAllowed;
	}

	public function canEditSubject( ?PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;
		return $this->editAllowed;
	}

	public function canDeleteSubject( ?PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;
		return $this->deleteAllowed;
	}

}
