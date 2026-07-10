<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Authorizes or denies every write, recording the last PageId it was asked about.
 */
class SpySubjectWriteAuthorizer implements SubjectWriteAuthorizer {

	public ?PageId $authorizedPageId = null;

	public function __construct(
		private bool $allowed
	) {
	}

	public function authorize( ?PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;
		return $this->allowed;
	}

}
