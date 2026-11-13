<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface UpdateStatementPresenter {

	/**
	 * Receives the persisted Subject, not the request: the server normalizes Statements on the way
	 * in, so this is the state a subsequent read returns. A null Schema means the Subject names a
	 * Schema that does not exist.
	 *
	 * $revisionId is the revision the Subject is now at, including after a write that changed nothing.
	 *
	 * @param Violation[] $violations
	 */
	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations, int $revisionId ): void;

	/**
	 * Called when the Statement changed after the base revision of the request, or that revision is not one of the
	 * Subject's page. Nothing was written. Receives the Subject as it is now.
	 */
	public function presentConflict( GetSubjectResponseItem $currentSubject, int $currentRevisionId ): void;

	/**
	 * Called when validation enforcement rejects an edit that would introduce
	 * new constraint violations relative to the Subject's prior state.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

	/**
	 * Called when the write did not land. $errorMessage is null when the write reported no message.
	 */
	public function presentSaveFailed( ?string $errorMessage ): void;

}
