<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject;

use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

readonly class ValidateSubjectQuery {

	public function __construct(
		private SchemaLookup $schemaLookup,
		private SubjectValidator $subjectValidator,
		private StatementListBuilder $statementListBuilder,
		private SelectStatementResolver $selectStatementResolver,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return Violation[]
	 *
	 * @throws \InvalidArgumentException when the schema name is invalid (empty etc.).
	 * @throws SchemaNotFoundException when the schema does not exist.
	 */
	public function validate( string $schemaName, string $label, array $statements ): array {
		$schema = $this->schemaLookup->getSchema( new SchemaName( $schemaName ) );

		if ( $schema === null ) {
			throw SchemaNotFoundException::forName( $schemaName );
		}

		return $this->subjectValidator->validate(
			new SubjectLabel( $label ),
			$this->statementListBuilder->build(
				$this->selectStatementResolver->resolveOrLeave( $schema, $statements )
			),
			$schema,
		);
	}

}
