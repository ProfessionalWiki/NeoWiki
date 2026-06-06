<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class CreateSubjectPresenterSpy implements CreateSubjectPresenter {

	public string $result = '';

	/** @var Violation[] */
	public array $violations = [];

	public bool $validationFailed = false;

	public function presentCreated( string $subjectId, array $violations ): void {
		$this->result = $subjectId;
		$this->violations = $violations;
	}

	public function presentSubjectAlreadyExists(): void {
		$this->result = 'presentSubjectAlreadyExists';
	}

	public function presentValidationFailed( array $violations ): void {
		$this->validationFailed = true;
		$this->violations = $violations;
	}

}
