<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface ReplaceSubjectPresenter {

	/**
	 * @param Violation[] $violations
	 */
	public function presentUpdated( string $subjectId, array $violations ): void;

}
