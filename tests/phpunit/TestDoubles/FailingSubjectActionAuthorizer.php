<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;

class FailingSubjectActionAuthorizer implements SubjectAuthorizer {

	public function canCreateMainSubject(): bool {
		return false;
	}

	public function canCreateChildSubject(): bool {
		return false;
	}

	public function canEditSubject(): bool {
		return false;
	}

	public function canDeleteSubject(): bool {
		return false;
	}

}
