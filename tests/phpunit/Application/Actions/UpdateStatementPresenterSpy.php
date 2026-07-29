<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement\UpdateStatementPresenter;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class UpdateStatementPresenterSpy implements UpdateStatementPresenter {

	public string $subjectId = '';

	/** @var Violation[] */
	public array $violations = [];

	public bool $validationFailed = false;

	public function presentUpdated( string $subjectId, array $violations ): void {
		$this->subjectId = $subjectId;
		$this->violations = $violations;
	}

	public function presentValidationFailed( array $violations ): void {
		$this->validationFailed = true;
		$this->violations = $violations;
	}

}
