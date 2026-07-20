<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use RuntimeException;

readonly class CreateSubjectAction {

	public function __construct(
		private CreateSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private IdGenerator $idGenerator,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaLookup $schemaLookup,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private bool $validationEnforced,
	) {
	}

	public function createSubject( CreateSubjectRequest $request ): void {
		if ( trim( $request->label ) === '' ) {
			throw new InvalidArgumentException( 'SubjectLabel cannot be empty' );
		}

		$pageId = new PageId( $request->pageId );

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to create this subject' );
		}

		$subject = $this->buildSubject( $request );

		if ( $request->id !== null && $this->subjectIdIsInUse( $subject->id ) ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageId );

		try {
			if ( $request->isMainSubject ) {
				$pageSubjects->createMainSubject( $subject );
			} else {
				$pageSubjects->createChildSubject( $subject );
			}
		} catch ( RuntimeException ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$violations = $this->proposedSubjectValidator->validate( $subject );

		if ( $this->validationEnforced && $this->blockingViolations( $violations ) !== [] ) {
			$this->presenter->presentValidationFailed( $violations );
			return;
		}

		$this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );
		$this->presenter->presentCreated( $subject->id->text, $violations );
	}

	/**
	 * @param Violation[] $violations
	 * @return Violation[]
	 */
	private function blockingViolations( array $violations ): array {
		return array_values( array_filter(
			$violations,
			static fn ( Violation $v ): bool => $v->isBlocking()
		) );
	}

	private function buildSubject( CreateSubjectRequest $request ): Subject {
		$schemaName = new SchemaName( $request->schemaName );
		$label = new SubjectLabel( $request->label );
		$statements = $this->statementListBuilder->build(
			$this->resolveSelectValues( $schemaName, $request->statements )
		);

		if ( $request->id === null ) {
			return Subject::createNew(
				idGenerator: $this->idGenerator,
				label: $label,
				schemaName: $schemaName,
				statements: $statements,
			);
		}

		return new Subject(
			id: new SubjectId( $request->id ),
			label: $label,
			schemaName: $schemaName,
			statements: $statements,
		);
	}

	/**
	 * Best-effort global uniqueness check: the subject -> page index lags slot writes, so this can
	 * miss a very recently created Subject; ID entropy carries the rest (same posture as relation IDs).
	 */
	private function subjectIdIsInUse( SubjectId $id ): bool {
		return $this->pageIdentifiersLookup->getPageIdOfSubject( $id ) !== null;
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return array<string, mixed>
	 */
	private function resolveSelectValues( SchemaName $schemaName, array $statements ): array {
		$schema = $this->schemaLookup->getSchema( $schemaName );

		if ( $schema === null ) {
			return $statements;
		}

		return $this->selectStatementResolver->resolve( $schema, $statements );
	}

}
