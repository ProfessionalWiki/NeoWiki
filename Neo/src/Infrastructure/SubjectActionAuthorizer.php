<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

/**
 * Fixme: this is the wrong NS
 */
interface SubjectActionAuthorizer {
	public function canCreateMainSubject(): bool;

	public function canCreateChildSubject(): bool;

	public function canEditSubject(): bool;

	public function canDeleteSubject(): bool;
}
