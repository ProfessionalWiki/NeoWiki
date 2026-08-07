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
	 * @param Violation[] $violations
	 */
	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void;

	/**
	 * Called when validation enforcement rejects an edit that would introduce
	 * new constraint violations relative to the Subject's prior state.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

}
