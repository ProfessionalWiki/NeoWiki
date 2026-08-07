<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement\UpdateStatementPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class UpdateStatementPresenterSpy implements UpdateStatementPresenter {

	public string $subjectId = '';

	public ?GetSubjectResponseItem $subject = null;

	public ?Schema $schema = null;

	/** @var Violation[] */
	public array $violations = [];

	public bool $validationFailed = false;

	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->subjectId = $subject->id;
		$this->subject = $subject;
		$this->schema = $schema;
		$this->violations = $violations;
	}

	public function presentValidationFailed( array $violations ): void {
		$this->validationFailed = true;
		$this->violations = $violations;
	}

}
