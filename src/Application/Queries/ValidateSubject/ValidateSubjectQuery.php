<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject;

use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

readonly class ValidateSubjectQuery {

	public function __construct(
		private SchemaResolver $schemaResolver,
		private SubjectValidator $subjectValidator,
		private StatementListBuilder $statementListBuilder,
		private SelectStatementResolver $selectStatementResolver,
		private string $localSourceKey,
	) {
	}

	/**
	 * @param mixed $schema A Schema reference as stored: a name for a Schema of this wiki, or a
	 *  `{source, name}` array for one from elsewhere (ADR 23).
	 * @param array<string, mixed> $statements
	 *
	 * @return Violation[]
	 *
	 * @throws \InvalidArgumentException when the reference is malformed (empty name etc.).
	 * @throws SchemaNotFoundException when no Source offers the Schema.
	 */
	public function validate( mixed $schema, array $statements ): array {
		$reference = SchemaReference::fromJson( $schema, $this->localSourceKey );
		$resolvedSchema = $this->schemaResolver->getSchema( $reference );

		if ( $resolvedSchema === null ) {
			throw SchemaNotFoundException::forName( $reference->getText() );
		}

		return $this->subjectValidator->validate(
			$this->statementListBuilder->build(
				$this->selectStatementResolver->resolveOrLeave( $resolvedSchema, $statements )
			),
			$resolvedSchema,
		);
	}

}
