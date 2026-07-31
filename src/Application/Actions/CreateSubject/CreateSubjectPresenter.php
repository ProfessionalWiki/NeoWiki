<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface CreateSubjectPresenter {

	/**
	 * Receives the persisted Subject, not the request: the server normalizes labels and Statements
	 * on the way in, so this is the state a subsequent read returns. A null Schema means the Subject
	 * names a Schema that does not exist.
	 *
	 * @param Violation[] $violations
	 */
	public function presentCreated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void;

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
