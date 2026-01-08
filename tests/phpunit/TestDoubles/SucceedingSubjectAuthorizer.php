<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;

class SucceedingSubjectAuthorizer implements SubjectAuthorizer {
	public function canCreateMainSubject(): bool {
		return true;
	}

	public function canCreateChildSubject(): bool {
		return true;
	}

	public function canEditSubject(): bool {
		return true;
	}

	public function canDeleteSubject(): bool {
		return true;
	}
}
