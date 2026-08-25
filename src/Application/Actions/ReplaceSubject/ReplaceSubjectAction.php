<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
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
		private SchemaLookup $schemaLookup,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private ReplaceSubjectPresenter $presenter,
		private bool $validationEnforced,
		private PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	/**
	 * Full replacement: a null label clears the stored one, exactly as an omitted property name
	 * deletes its Statement.
	 *
	 * @param array<string, mixed> $statements
	 */
	public function replace( SubjectId $subjectId, ?string $label, array $statements, ?string $comment ): void {
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

		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$subject->setLabel( SubjectLabel::fromText( $label ) );
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
			GetSubjectResponseItem::fromSubject(
				$subject,
				$pageIdentifiers,
				$this->getDisplayName( $subject, $pageIdentifiers )
			),
			$schema,
			$proposedViolations
		);
	}

	/**
	 * Which Subject the page treats as its own topic decides what a Subject without a label is called,
	 * and only the page knows that. A replace cannot change it, so reading it after the write is safe.
	 */
	private function getDisplayName( Subject $subject, PageIdentifiers $pageIdentifiers ): string {
		return SubjectDisplayName::forSubjectIn(
			$subject,
			$this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() ),
			$pageIdentifiers->getTitle()
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
