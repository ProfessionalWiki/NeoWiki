<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

/**
 * Creates a Subject together with the page it lives on, in one revision, for callers who have a
 * thing to describe rather than a page to describe it on.
 *
 * The page is titled by the Subject's label, and by the Subject's own id when no label titles one.
 * No title is ever invented from a taken one: a collision goes back to the caller, who knows
 * whether they meant that page.
 */
readonly class CreateSubjectPageAction {

	public function __construct(
		private CreateSubjectPagePresenter $presenter,
		private SubjectRepository $subjectRepository,
		private IdGenerator $idGenerator,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private PageIdentifiersResolver $pageIdentifiersResolver,
		private bool $validationEnforced,
	) {
	}

	public function createSubjectPage( CreateSubjectPageRequest $request ): void {
		$schemaReference = SchemaReference::local( new SchemaName( $request->schemaName ) );
		$schema = $this->schemaResolver->getSchema( $schemaReference );

		$subject = $this->buildSubject( $request, $schemaReference, $schema );
		$pageTitle = $this->pageTitleFor( $subject->getId(), $request->label );

		// Authorized before the page is looked for, so that a title the caller may not write
		// answers the same whether or not a page holds it, rather than reporting one they may
		// not touch.
		if ( !$this->writeAuthorizer->authorizeCreatePage( $pageTitle ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to create this page' );
		}

		if ( $this->pageIdentifiersResolver->getIdentifiersOfTitle( $pageTitle ) !== null ) {
			$this->presenter->presentPageTitleTaken( $pageTitle );
			return;
		}

		$violations = $this->proposedSubjectValidator->validate( $subject );

		if ( $this->validationEnforced && $this->blockingViolations( $violations ) !== [] ) {
			$this->presenter->presentValidationFailed( $violations );
			return;
		}

		$pageSubjects = new PageSubjects( $subject, new SubjectMap() );
		$status = $this->subjectRepository->createPageWithSubjects( $pageTitle, $pageSubjects, $request->comment );

		if ( $status->pageId === null ) {
			$this->presentFailedWrite( $pageTitle, $status );
			return;
		}

		$page = new PageIdentifiers( id: $status->pageId, title: $pageTitle, namespaceId: NS_MAIN );

		$this->presenter->presentCreated(
			GetSubjectResponseItem::fromSubject(
				$subject,
				$page,
				SubjectDisplayName::labelOrPageNameIn( $subject, $pageSubjects, $pageTitle )
			),
			$page,
			$schema,
			$violations
		);
	}

	/**
	 * The write refuses a page that exists, which is the check the one before it cannot make: that
	 * one reads a replica, so a page created in the meantime is invisible to it. Looking again is
	 * what tells that lost race from a write that failed for a reason of its own.
	 */
	private function presentFailedWrite( string $pageTitle, PageContentSavingStatus $status ): void {
		if ( $this->pageIdentifiersResolver->getIdentifiersOfTitle( $pageTitle ) !== null ) {
			$this->presenter->presentPageTitleTaken( $pageTitle );
			return;
		}

		$this->presenter->presentPageNotCreated( $pageTitle, $status->errorMessage );
	}

	/**
	 * The page a Subject gets to itself: the page its label titles, and the page its own id titles
	 * when the label titles none.
	 */
	private function pageTitleFor( SubjectId $subjectId, ?string $label ): string {
		$fromLabel = $label === null ? null : $this->pageIdentifiersResolver->getMainNamespaceTitle( $label );

		// A Subject id titles a page whatever the label does: its grammar (ADR 14) holds none of
		// the characters MediaWiki refuses in a title. Normalized all the same, since a wiki that
		// capitalizes page titles - the default - stores it under an upper-case S.
		return $fromLabel
			?? $this->pageIdentifiersResolver->getMainNamespaceTitle( $subjectId->text )
			?? $subjectId->text;
	}

	private function buildSubject(
		CreateSubjectPageRequest $request,
		SchemaReference $schemaReference,
		?Schema $schema
	): Subject {
		return Subject::createNew(
			idGenerator: $this->idGenerator,
			label: SubjectLabel::fromText( $request->label ),
			schema: $schemaReference,
			statements: $this->statementListBuilder->build(
				$this->resolveSelectValues( $schema, $request->statements )
			),
		);
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return array<string, mixed>
	 */
	private function resolveSelectValues( ?Schema $schema, array $statements ): array {
		if ( $schema === null ) {
			return $statements;
		}

		return $this->selectStatementResolver->resolve( $schema, $statements );
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

}
