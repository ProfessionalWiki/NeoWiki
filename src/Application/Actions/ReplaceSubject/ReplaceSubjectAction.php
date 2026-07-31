<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

readonly class ReplaceSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectWriteAuthorizer $writeAuthorizer,
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

		// Null identifiers (unresolvable Subject) make the authorizer fall back to the global 'edit'
		// right. This cannot bypass page protection: an unresolvable Subject is not found below
		// (getSubject returns null), so the request 404s before any write rather than touching a
		// protected page.
		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId );

		if ( !$this->writeAuthorizer->authorize( $pageIdentifiers?->getId() ) ) {
			throw new SubjectEditNotAuthorizedException();
		}

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$subject->setLabel( new SubjectLabel( $label ) );
		$subject->setStatements(
			$this->statementListBuilder->build( $this->resolveStatements( $schema, $statements ) )
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

		// The mutated Subject is the persisted state: the builder and the resolver above already
		// normalized what the request supplied.
		$this->presenter->presentUpdated(
			GetSubjectResponseItem::fromSubject( $subject, $pageIdentifiers ),
			$schema,
			$proposedViolations
		);
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return array<string, mixed>
	 */
	private function resolveStatements( ?Schema $schema, array $statements ): array {
		if ( $schema === null ) {
			return $statements;
		}

		return $this->selectStatementResolver->resolve( $schema, $statements );
	}

}
