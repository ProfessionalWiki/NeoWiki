<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

readonly class ReplaceSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaLookup $schemaLookup,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private ReplaceSubjectPresenter $presenter,
		private bool $validationEnforced,
		private PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 */
	public function replace( SubjectId $subjectId, string $label, array $statements, ?string $comment ): void {
		if ( trim( $label ) === '' ) {
			throw new InvalidArgumentException( 'SubjectLabel cannot be empty' );
		}

		// A null pageId (unresolvable Subject) makes the authorizer fall back to the global 'edit' right.
		// This cannot bypass page protection: an unresolvable Subject has no content to replace, so the
		// update below is a no-op rather than a write to a protected page.
		$pageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		if ( !$this->subjectAuthorizer->canEditSubject( $pageId ) ) {
			throw new SubjectEditNotAuthorizedException();
		}

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$subject->setLabel( new SubjectLabel( $label ) );
		$subject->setStatements(
			$this->statementListBuilder->build( $this->resolveStatements( $subject, $statements ) )
		);

		$proposedViolations = $this->proposedSubjectValidator->validate( $subject );

		$newBlockingViolations = array_filter(
			ViolationDiff::newViolations( $proposedViolations, $priorViolations ),
			static fn ( Violation $v ): bool => $v->isBlocking()
		);

		if ( $this->validationEnforced && $newBlockingViolations !== [] ) {
			$this->presenter->presentValidationFailed( $proposedViolations );
			return;
		}

		$this->subjectRepository->updateSubject( $subject, $comment );

		$this->presenter->presentUpdated( $subjectId->text, $proposedViolations );
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return array<string, mixed>
	 */
	private function resolveStatements( Subject $subject, array $statements ): array {
		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		if ( $schema === null ) {
			return $statements;
		}

		return $this->selectStatementResolver->resolve( $schema, $statements );
	}

}
