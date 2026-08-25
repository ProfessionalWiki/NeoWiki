<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
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
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaLookup $schemaLookup,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private UpdateStatementPresenter $presenter,
		private bool $validationEnforced,
		private PageIdentifiersLookup $pageIdentifiersLookup,
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
		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

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
			$this->schemaLookup->getSchema( $subject->getSchemaName() ),
			$pageIdentifiers,
			$comment
		);
	}

	private function getPageOfSubjectToEdit( SubjectId $subjectId ): PageIdentifiers {
		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId );

		// Gate on read before write: a page the caller may not read answers exactly like a Subject
		// that does not exist, so restricted pages cannot be told apart from absent ones - and the
		// page title and namespace this endpoint returns never reach a caller denied the page. An
		// unresolvable Subject takes that same path, since it has no page to authorize against.
		// Reaching the write check with null identifiers would answer 403 where a restricted page
		// answers 404, telling a caller who lacks the wiki-global 'edit' right which of the
		// Subject ids they hold exist. Only a Subject on a page the caller can read (its existence
		// already public) proceeds to the write check and its 403.
		if ( $pageIdentifiers === null
			|| !$this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() ) ) {
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
			GetSubjectResponseItem::fromSubject(
				$proposedSubject,
				$pageIdentifiers,
				$this->getDisplayName( $proposedSubject, $pageIdentifiers )
			),
			$schema,
			$proposedViolations
		);
	}

	/**
	 * Which Subject the page treats as its own topic decides what a Subject without a label is called,
	 * and only the page knows that. Setting a Statement cannot change it.
	 */
	private function getDisplayName( Subject $subject, PageIdentifiers $pageIdentifiers ): string {
		return SubjectDisplayName::forSubjectIn(
			$subject,
			$this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() ),
			$pageIdentifiers->getTitle()
		);
	}

}
