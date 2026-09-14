<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

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
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private UpdateStatementPresenter $presenter,
		private bool $validationEnforced,
	) {
	}

	/**
	 * @param mixed $propertyType The writer's type for the value, as the caller supplied it. Falls
	 *  back to the type the Subject's Schema currently gives the property when null. Taken raw
	 *  rather than as a string because the request body it comes from is unvalidated below its top
	 *  level; the builder rejects anything that is not a string.
	 *
	 * @throws InvalidArgumentException When the property type is not a string, when none is given
	 *  and none can be derived, or when a select value cannot be resolved.
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function setStatement(
		SubjectId $subjectId,
		PropertyName $propertyName,
		mixed $propertyType,
		mixed $value,
		?string $comment
	): void {
		$pageIdentifiers = $this->getPageOfSubjectToEdit( $subjectId );
		$subject = $this->getSubject( $subjectId );
		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		$statement = $this->buildStatement( $schema, $propertyName, $propertyType, $value );

		$this->save(
			$subject,
			$statement === null
				? $subject->getStatements()->withoutStatement( $propertyName )
				: $subject->getStatements()->withStatement( $statement ),
			$schema,
			$pageIdentifiers,
			$comment
		);
	}

	/**
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function removeStatement( SubjectId $subjectId, PropertyName $propertyName, ?string $comment ): void {
		$pageIdentifiers = $this->getPageOfSubjectToEdit( $subjectId );
		$subject = $this->getSubject( $subjectId );

		$this->save(
			$subject,
			$subject->getStatements()->withoutStatement( $propertyName ),
			$this->schemaResolver->getSchema( $subject->getSchemaReference() ),
			$pageIdentifiers,
			$comment
		);
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

	private function getSubject( SubjectId $subjectId ): Subject {
		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		return $subject;
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

		if ( $schema !== null ) {
			$statements = $this->selectStatementResolver->resolve( $schema, $statements );
		}

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

	private function save(
		Subject $subject,
		StatementList $statements,
		?Schema $schema,
		PageIdentifiers $pageIdentifiers,
		?string $comment
	): void {
		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$proposedSubject = $subject->withStatements( $statements );
		$proposedViolations = $this->proposedSubjectValidator->validate( $proposedSubject );

		$newBlockingViolations = array_filter(
			ViolationDiff::newViolations( $proposedViolations, $priorViolations ),
			static fn ( Violation $v ): bool => $v->isBlocking()
		);

		if ( $this->validationEnforced && $newBlockingViolations !== [] ) {
			$this->presenter->presentValidationFailed( $proposedViolations );
			return;
		}

		$this->subjectRepository->updateSubject( $proposedSubject, $comment );

		// The proposed Subject is the persisted state: the builder and the resolver above already
		// normalized what the request supplied.
		$this->presenter->presentUpdated(
			$this->newResponseItem( $proposedSubject, $pageIdentifiers ),
			$schema,
			$proposedViolations
		);
	}

	/**
	 * Which Subject the page treats as its own topic decides what a Subject without a label is called.
	 * Only the page knows that. Setting a Statement cannot change it.
	 */
	private function newResponseItem( Subject $subject, PageIdentifiers $pageIdentifiers ): GetSubjectResponseItem {
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() );

		return GetSubjectResponseItem::fromSubject(
			$subject,
			$pageIdentifiers,
			SubjectDisplayName::labelOrPageNameIn( $subject, $pageSubjects, $pageIdentifiers->getTitle() )
		);
	}

}
