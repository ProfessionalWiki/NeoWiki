<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectPresenter;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class ReplaceSubjectPresenterSpy implements ReplaceSubjectPresenter {

	public string $subjectId = '';

	/** @var Violation[] */
	public array $violations = [];

	public function presentUpdated( string $subjectId, array $violations ): void {
		$this->subjectId = $subjectId;
		$this->violations = $violations;
	}

}
