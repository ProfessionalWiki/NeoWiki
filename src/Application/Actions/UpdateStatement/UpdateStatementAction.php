<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement;

use Closure;
use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\EditConflictCheck;
use ProfessionalWiki\NeoWiki\Application\SubjectAtRevision;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

/**
 * Sets or removes one Statement of a Subject, leaving its other Statements and its label alone.
 */
readonly class UpdateStatementAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private DependentSubjectRules $dependentSubjectRules,
		private EditConflictCheck $editConflictCheck,
		private SubjectResponseItemFactory $responseItemFactory,
		private UpdateStatementPresenter $presenter,
		private bool $validationEnforced,
	) {
	}

	/**
	 * @param int $baseRevisionId The revision the request was made from. The write is refused when the
	 *  Statement changed after it; changes to the Subject's other Statements and to other Subjects pass.
	 * @param mixed $propertyType The writer's type for the value, as the caller supplied it. Falls
	 *  back to the type the Subject's Schema currently gives the property when null. Taken raw
	 *  rather than as a string because the request body it comes from is unvalidated below its top
	 *  level; the builder rejects anything that is not a string.
	 *
	 * @throws InvalidArgumentException When the property type is not a string, when none is given
	 *  and none can be derived, or when the value does not fit its property type.
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function setStatement(
		SubjectId $subjectId,
		int $baseRevisionId,
		PropertyName $propertyName,
		mixed $propertyType,
		mixed $value,
		?string $comment
	): void {
		$this->write(
			$subjectId,
			$baseRevisionId,
			$propertyName,
			$comment,
			function ( Subject $subject, ?Schema $schema ) use ( $propertyName, $propertyType, $value ): StatementList {
				$statement = $this->buildStatement( $schema, $propertyName, $propertyType, $value );

				return $statement === null
					? $subject->getStatements()->withoutStatement( $propertyName )
					: $subject->getStatements()->withStatement( $statement );
			}
		);
	}

	/**
	 * @param int $baseRevisionId As for {@see self::setStatement()}.
	 *
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function removeStatement( SubjectId $subjectId, int $baseRevisionId, PropertyName $propertyName, ?string $comment ): void {
		$this->write(
			$subjectId,
			$baseRevisionId,
			$propertyName,
			$comment,
			static fn ( Subject $subject ): StatementList => $subject->getStatements()->withoutStatement( $propertyName )
		);
	}

	/**
	 * @param Closure(Subject, ?Schema): StatementList $changeStatements
	 *
	 * @throws InvalidArgumentException
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	private function write(
		SubjectId $subjectId,
		int $baseRevisionId,
		PropertyName $propertyName,
		?string $comment,
		Closure $changeStatements
	): void {
		$pageIdentifiers = $this->getPageOfSubjectToEdit( $subjectId );

		$landed = $this->editConflictCheck->attemptUntilLanded(
			fn (): bool => $this->writeOnLatestRevision( $subjectId, $baseRevisionId, $propertyName, $pageIdentifiers, $comment, $changeStatements )
		);

		if ( !$landed ) {
			$this->presenter->presentSaveFailed( EditConflictCheck::PAGE_KEPT_CHANGING );
		}
	}

	/**
	 * @param Closure(Subject, ?Schema): StatementList $changeStatements
	 *
	 * @return bool False when another save landed after the read, so nothing was written or presented.
	 *
	 * @throws InvalidArgumentException
	 * @throws SubjectNotFoundException
	 */
	private function writeOnLatestRevision(
		SubjectId $subjectId,
		int $baseRevisionId,
		PropertyName $propertyName,
		PageIdentifiers $pageIdentifiers,
		?string $comment,
		Closure $changeStatements
	): bool {
		$latest = $this->editConflictCheck->readLatest( $pageIdentifiers->getId(), $subjectId );
		$overwritten = fn ( Subject $subject, PageSubjects $pageSubjects ): SubjectMap =>
			$this->statementWithDependentSubjects( $subject, $propertyName, $pageSubjects );

		if ( $this->editConflictCheck->changedSince( $baseRevisionId, $latest, $pageIdentifiers->getId(), $overwritten ) ) {
			$this->presenter->presentConflict(
				$this->responseItemFactory->createResponseItem( $latest->subject, $pageIdentifiers, $latest->pageSubjects ),
				$latest->revisionId
			);
			return true;
		}

		$schema = $this->schemaResolver->getSchema( $latest->subject->getSchemaReference() );

		return $this->save( $latest, $changeStatements( $latest->subject, $schema ), $schema, $pageIdentifiers, $comment );
	}

	/**
	 * The Statement, on a Subject holding nothing else, and the Dependent Subjects it reaches, which a write that
	 * drops the Statement removes with it.
	 */
	private function statementWithDependentSubjects( Subject $subject, PropertyName $propertyName, PageSubjects $pageSubjects ): SubjectMap {
		$statement = $subject->getStatements()->getStatement( $propertyName );
		$holder = Subject::newSubject( $subject->id, null, $subject->getSchemaReference() )
			->withStatements( new StatementList( $statement === null ? [] : [ $statement ] ) );

		return $this->dependentSubjectRules->dependentSubjectsReachedFrom( $holder, $pageSubjects )->prepend( $holder );
	}

	private function getPageOfSubjectToEdit( SubjectId $subjectId ): PageIdentifiers {
		$pageIdentifiers = $this->hostingPageResolver->resolveReadableHostingPage( $subjectId );

		if ( $pageIdentifiers === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		if ( !$this->writeAuthorizer->authorize( $pageIdentifiers->getId() ) ) {
			throw new SubjectEditNotAuthorizedException();
		}

		return $pageIdentifiers;
	}

	/**
	 * Returns null when the value is empty for its type, which the write paths treat as
	 * the Statement being absent.
	 */
	private function buildStatement(
		?Schema $schema,
		PropertyName $propertyName,
		mixed $propertyType,
		mixed $value
	): ?Statement {
		$statements = [
			$propertyName->text => [
				'propertyType' => $propertyType ?? $this->getSchemaPropertyType( $schema, $propertyName ),
				'value' => $value,
			],
		];

		return $this->statementListBuilder->build( $statements )->getStatement( $propertyName );
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function getSchemaPropertyType( ?Schema $schema, PropertyName $propertyName ): string {
		if ( $schema !== null && $schema->hasProperty( $propertyName ) ) {
			return $schema->getProperty( $propertyName )->getPropertyType();
		}

		throw new InvalidArgumentException(
			"propertyType is required for \"{$propertyName->text}\": "
				. "the Subject's Schema does not define the property"
		);
	}

	/**
	 * @return bool False when another save landed after the read, so nothing was written or presented.
	 */
	private function save(
		SubjectAtRevision $latest,
		StatementList $statements,
		?Schema $schema,
		PageIdentifiers $pageIdentifiers,
		?string $comment
	): bool {
		$priorViolations = $this->proposedSubjectValidator->validate( $latest->subject );

		$proposedSubject = $latest->subject->withStatements( $statements );
		$proposedViolations = $this->proposedSubjectValidator->validate( $proposedSubject );

		$newBlockingViolations = array_filter(
			ViolationDiff::newViolations( $proposedViolations, $priorViolations ),
			static fn ( Violation $v ): bool => $v->isBlocking()
		);

		if ( $this->validationEnforced && $newBlockingViolations !== [] ) {
			$this->presenter->presentValidationFailed( $proposedViolations );
			return true;
		}

		try {
			$status = $this->subjectRepository->updateSubject( $proposedSubject, $latest->revisionId, $comment, new SubjectMap() );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			$this->presenter->presentValidationFailed( array_merge( $proposedViolations, $exception->violations ) );
			return true;
		}

		if ( $status->status === PageContentSavingStatus::CONFLICT ) {
			return false;
		}

		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentSaveFailed( $status->errorMessage );
			return true;
		}

		// The proposed Subject is the persisted state. Which Subject the page treats as its own topic
		// decides what a Subject without a label is called, and setting a Statement cannot change it.
		$this->presenter->presentUpdated(
			$this->responseItemFactory->createResponseItem( $proposedSubject, $pageIdentifiers, $latest->pageSubjects ),
			$schema,
			$proposedViolations,
			$status->revisionId ?? $latest->revisionId
		);
		return true;
	}

}
