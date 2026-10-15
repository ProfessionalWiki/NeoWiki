<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface SetSubjectsOrderingPresenter {

	public function presentOrderingChanged(): void;

	public function presentNoChange(): void;

	public function presentInvalidOrdering( string $reason ): void;

	/**
	 * Called when the target page cannot be resolved: it does not exist, or the caller may not read
	 * it. Both take this one shape so a caller cannot tell a hidden page apart from an absent one by
	 * sweeping page ids (see PageReadAuthorizer).
	 */
	public function presentPageNotFound(): void;

	/**
	 * Called when the ordering breaks a rule for Dependent Subjects. Nothing is changed.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

}
