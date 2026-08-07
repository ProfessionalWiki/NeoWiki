<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
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
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
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

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$subject->setLabel( new SubjectLabel( $label ) );
		$subject->setStatements(
			$this->statementListBuilder->build( $this->resolveStatements( $schema, $statements ) )
		);

		$proposedViolations = $this->proposedSubjectValidator->validate( $subject );

		// Only violations this edit introduces block it, so a Subject that already carries one stays
		// editable rather than being frozen by it.
		$newBlockingViolations = array_filter(
			ViolationDiff::newViolations( $proposedViolations, $priorViolations ),
			fn ( Violation $v ): bool => $v->alwaysBlocksWrites() || ( $this->validationEnforced && $v->isBlocking() )
		);

		if ( $newBlockingViolations !== [] ) {
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
