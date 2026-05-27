<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface CreateSubjectPresenter {

	/** @param Violation[] $violations */
	public function presentCreated( string $subjectId, array $violations ): void;

	public function presentSubjectAlreadyExists(): void;

}
