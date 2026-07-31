<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class CreateSubjectPresenterSpy implements CreateSubjectPresenter {

	public string $result = '';

	public ?GetSubjectResponseItem $subject = null;

	public ?Schema $schema = null;

	/** @var Violation[] */
	public array $violations = [];

	public bool $validationFailed = false;

	public function presentCreated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->result = $subject->id;
		$this->subject = $subject;
		$this->schema = $schema;
		$this->violations = $violations;
	}

	public function presentSubjectAlreadyExists(): void {
		$this->result = 'presentSubjectAlreadyExists';
	}

	public function presentPageNotFound(): void {
		$this->result = 'presentPageNotFound';
	}

	public function presentValidationFailed( array $violations ): void {
		$this->validationFailed = true;
		$this->violations = $violations;
	}

}
