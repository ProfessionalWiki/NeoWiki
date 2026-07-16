<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubjectUpdate;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

readonly class ValidateSubjectUpdateQuery {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SchemaLookup $schemaLookup,
		private SubjectValidator $subjectValidator,
		private StatementListBuilder $statementListBuilder,
		private SelectStatementResolver $selectStatementResolver,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private SubjectReadAuthorizer $readAuthorizer,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return Violation[]
	 *
	 * @throws \InvalidArgumentException when the subject id format is invalid.
	 * @throws SubjectNotFoundException when the subject does not exist or the caller may not read its page.
	 */
	public function validate( string $subjectId, string $label, array $statements ): array {
		$id = new SubjectId( $subjectId );
		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $id );

		if ( $pageIdentifiers === null ) {
			// No owning page in the graph means the repository cannot load the Subject either.
			throw SubjectNotFoundException::forId( $id );
		}

		if ( !$this->readAuthorizer->authorizeRead( $pageIdentifiers->getId() ) ) {
			// Denial is shaped exactly like absence: this endpoint previously oracled Subject
			// existence via its 404, so denied and absent must stay indistinguishable (#1046).
			throw SubjectNotFoundException::forId( $id );
		}

		$subject = $this->subjectRepository->getSubject( $id );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $id );
		}

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
			new SubjectLabel( $label ),
			$this->statementListBuilder->build(
				$this->selectStatementResolver->resolveOrLeave( $schema, $statements )
			),
			$schema,
		);
	}

}
