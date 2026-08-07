<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * Validates a proposed Subject against its current Schema, looking the Schema
 * up by the Subject's Schema name and delegating to {@see SubjectValidator}.
 *
 * When the Schema cannot be found, a single non-blocking `schema-not-found`
 * violation is returned and the write still proceeds: the Subject stays
 * editable (ADR 21), but the response reports that it could not be validated
 * rather than implying it is valid. This matches the update-validate endpoint,
 * which emits the same violation. Centralising the decision keeps the two write
 * paths (CreateSubjectAction, ReplaceSubjectAction) from repeating it, and is
 * where the enforcement tier will hook in. ValidateSubjectUpdateQuery still has
 * its own copy until the validate and write flows are unified.
 */
readonly class ProposedSubjectValidator {

	public function __construct(
		private SchemaResolver $schemaResolver,
		private SubjectValidator $subjectValidator,
	) {
	}

	/**
	 * @return Violation[]
	 */
	public function validate( Subject $subject ): array {
		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		if ( $schema === null ) {
			// Everything Schema-scoped is lost, but a relation target no Source can reach still is
			// one: it would be written unreadable whether or not the Schema turns up later.
			return array_merge(
				[
					new Violation(
						propertyName: null,
						code: 'schema-not-found',
						args: [ $subject->getSchemaReference()->getText() ],
						severity: Severity::Warning,
					),
				],
				$this->subjectValidator->validateRelationTargetSources( $subject->getStatements() )
			);
		}

		return $this->subjectValidator->validate(
			$subject->getLabel(),
			$subject->getStatements(),
			$schema,
		);
	}

}
