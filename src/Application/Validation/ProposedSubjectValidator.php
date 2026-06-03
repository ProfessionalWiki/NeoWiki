<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * Validates a proposed Subject against its current Schema, looking the Schema
 * up by the Subject's Schema name and delegating to {@see SubjectValidator}.
 *
 * When the Schema cannot be found, no violations are returned: the Subject is
 * left unvalidated so the write can still proceed and the Subject stays
 * editable (ADR 21). Centralising that decision here keeps the write paths
 * (CreateSubjectAction, ReplaceSubjectAction) from each repeating it.
 */
readonly class ProposedSubjectValidator {

	public function __construct(
		private SchemaLookup $schemaLookup,
		private SubjectValidator $subjectValidator,
	) {
	}

	/**
	 * @return Violation[]
	 */
	public function validate( Subject $subject ): array {
		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		if ( $schema === null ) {
			return [];
		}

		return $this->subjectValidator->validate(
			$subject->getLabel(),
			$subject->getStatements(),
			$schema,
		);
	}

}
