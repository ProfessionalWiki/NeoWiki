<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;

class SucceedingSubjectActionAuthorizer implements SubjectActionAuthorizer {
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
