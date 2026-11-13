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

	public ?string $saveFailure = null;

	public ?int $revisionId = null;

	public ?GetSubjectResponseItem $conflictingSubject = null;

	public ?int $conflictRevisionId = null;

	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations, int $revisionId ): void {
		$this->subjectId = $subject->id;
		$this->subject = $subject;
		$this->schema = $schema;
		$this->violations = $violations;
		$this->revisionId = $revisionId;
	}

	public function presentConflict( GetSubjectResponseItem $currentSubject, int $currentRevisionId ): void {
		$this->conflictingSubject = $currentSubject;
		$this->conflictRevisionId = $currentRevisionId;
	}

	public function presentValidationFailed( array $violations ): void {
		$this->validationFailed = true;
		$this->violations = $violations;
	}

	public function presentSaveFailed( ?string $errorMessage ): void {
		$this->saveFailure = $errorMessage ?? '';
	}

}
