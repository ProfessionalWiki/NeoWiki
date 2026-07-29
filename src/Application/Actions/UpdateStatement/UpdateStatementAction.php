<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

/**
 * Sets or removes one Statement of a Subject, leaving its other Statements and its label alone.
 */
readonly class UpdateStatementAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
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
	 * @param string|null $propertyType The writer's type for the value. Falls back to the type the
	 *  Subject's Schema currently gives the property.
	 *
	 * @throws InvalidArgumentException When no property type is given and none can be derived,
	 *  or when a select value cannot be resolved.
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function setStatement(
		SubjectId $subjectId,
		PropertyName $propertyName,
		?string $propertyType,
		mixed $value,
		?string $comment
	): void {
		$subject = $this->getSubjectToEdit( $subjectId );
		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		$statement = $this->buildStatement( $schema, $propertyName, $propertyType, $value );

		$this->save(
			$subject,
			$statement === null
				? $subject->getStatements()->withoutStatement( $propertyName )
				: $subject->getStatements()->withStatement( $statement ),
			$comment
		);
	}

	/**
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function removeStatement( SubjectId $subjectId, PropertyName $propertyName, ?string $comment ): void {
		$subject = $this->getSubjectToEdit( $subjectId );

		$this->save( $subject, $subject->getStatements()->withoutStatement( $propertyName ), $comment );
	}

	private function getSubjectToEdit( SubjectId $subjectId ): Subject {
		// A null pageId (unresolvable Subject) makes the authorizer fall back to the global 'edit' right.
		// This cannot bypass page protection: an unresolvable Subject is not found below (getSubject
		// returns null), so the request 404s before any write rather than touching a protected page.
		$pageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new SubjectEditNotAuthorizedException();
		}

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
		?string $propertyType,
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

	private function save( Subject $subject, StatementList $statements, ?string $comment ): void {
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

		$this->presenter->presentUpdated( $proposedSubject->getId()->text, $proposedViolations );
	}

}
