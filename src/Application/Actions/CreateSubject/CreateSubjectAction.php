<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
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
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

readonly class CreateSubjectAction {

	public function __construct(
		private CreateSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private IdGenerator $idGenerator,
		private PageReadAuthorizer $readAuthorizer,
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

		// Gate on read before write, and before touching any page state: a page the caller may not
		// read, and a page that does not exist, both answer the same not-found shape, so restricted
		// pages cannot be told apart from absent ones by sweeping page ids. Only a page the caller
		// can read (its existence already public) proceeds to the write check and its 403.
		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageId ) ) {
			$this->presenter->presentPageNotFound();
			return;
		}

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

		$status = $this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );

		// The read gate above already turns an unresolvable page away; this catches the page going
		// away between that check and the save, so a dropped write is never reported as created.
		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentPageNotFound();
			return;
		}

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
