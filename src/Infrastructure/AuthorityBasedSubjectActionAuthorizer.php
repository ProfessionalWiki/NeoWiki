<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;

class AuthorityBasedSubjectActionAuthorizer implements SubjectAuthorizer {

	public function __construct(
		private Authority $authority
	) {
	}

	public function canCreateMainSubject(): bool {
		return $this->authority->isAllowed( 'edit' );
	}

	public function canCreateChildSubject(): bool {
		return $this->authority->isAllowed( 'edit' );
	}

	public function canEditSubject(): bool {
		return $this->authority->isAllowed( 'edit' );
	}

	public function canDeleteSubject(): bool {
		return $this->authority->isAllowed( 'edit' );
	}

}
