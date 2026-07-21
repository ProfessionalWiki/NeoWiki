<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface CreateSubjectPresenter {

	/** @param Violation[] $violations */
	public function presentCreated( string $subjectId, array $violations ): void;

	public function presentSubjectAlreadyExists(): void;

	/**
	 * Called when the target page cannot be resolved: it does not exist, or the caller may not read
	 * it. Both take this one shape so a caller cannot tell a hidden page apart from an absent one by
	 * sweeping page ids (see PageReadAuthorizer).
	 */
	public function presentPageNotFound(): void;

	/**
	 * Called when validation enforcement rejects a Subject the request would
	 * have created.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

}
