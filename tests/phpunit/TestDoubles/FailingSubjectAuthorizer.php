<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class FailingSubjectAuthorizer implements SubjectAuthorizer {
	public function canCreateMainSubject( ?PageId $pageId ): bool {
		return false;
	}

	public function canCreateChildSubject( ?PageId $pageId ): bool {
		return false;
	}

	public function canEditSubject( ?PageId $pageId ): bool {
		return false;
	}

	public function canDeleteSubject( ?PageId $pageId ): bool {
		return false;
	}
}
