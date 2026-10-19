<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * When the Schema cannot be found, a non-blocking `schema-not-found` violation
 * stands in for the Schema-scoped checks, so the Subject stays editable but is
 * not reported as valid.
 */
readonly class ProposedSubjectValidator {

	public function __construct(
		private SchemaResolver $schemaResolver,
		private SubjectValidator $subjectValidator,
	) {
	}

	/**
	 * @param SubjectMap $writtenAlongside Subjects stored by the same write, as it leaves them. They count as existing
	 *   relation targets.
	 *
	 * @return Violation[]
	 */
	public function validate( Subject $subject, SubjectMap $writtenAlongside = new SubjectMap() ): array {
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
			$subject->getStatements(),
			$schema,
			$writtenAlongside,
		);
	}

}
