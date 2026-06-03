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
 * When the Schema cannot be found, a single non-blocking `schema-not-found`
 * violation is returned and the write still proceeds: the Subject stays
 * editable (ADR 21), but the response reports that it could not be validated
 * rather than implying it is valid. This matches the update-validate endpoint,
 * which emits the same violation. Centralising the decision here keeps the
 * write paths (CreateSubjectAction, ReplaceSubjectAction) from each repeating
 * it and gives the future enforcement tier one place to reason about it.
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
			return [
				new Violation(
					propertyName: null,
					code: 'schema-not-found',
					args: [ $subject->getSchemaName()->getText() ],
				),
			];
		}

		return $this->subjectValidator->validate(
			$subject->getLabel(),
			$subject->getStatements(),
			$schema,
		);
	}

}
