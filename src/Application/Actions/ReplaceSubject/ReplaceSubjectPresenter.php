<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface ReplaceSubjectPresenter {

	/**
	 * @param Violation[] $violations
	 */
	public function presentUpdated( string $subjectId, array $violations ): void;

	/**
	 * Called when validation enforcement rejects an edit that would introduce
	 * new constraint violations relative to the Subject's prior state.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

}
