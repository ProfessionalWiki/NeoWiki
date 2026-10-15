<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface SetMainSubjectPresenter {

	public function presentMainSubjectChanged(): void;

	public function presentNoChange(): void;

	public function presentSubjectNotFound(): void;

	/**
	 * Called when the target page cannot be resolved: it does not exist, or the caller may not read
	 * it. Both take this one shape so a caller cannot tell a hidden page apart from an absent one by
	 * sweeping page ids (see PageReadAuthorizer).
	 */
	public function presentPageNotFound(): void;

	/**
	 * Called when the change breaks a rule for Dependent Subjects. Nothing is changed.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

}
