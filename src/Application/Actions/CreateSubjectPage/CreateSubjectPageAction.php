<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage;

use ProfessionalWiki\NeoWiki\Application\NewSubjectIdResolver;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectNamer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

/**
 * Creates a Subject together with the page it lives on, in one revision, for callers who have a
 * thing to describe rather than a page to describe it on.
 *
 * The page is titled by the title the caller chose, by the Subject's label where they chose none, by
 * its template label where it has none, and by the Subject's own id when neither titles one. No title
 * is ever invented from one the caller chose: a title already taken, and one that titles no page here,
 * both go back to them. A template label was chosen by nobody, so a page it would title that exists,
 * or that the caller may not create, leaves the Subject its id instead.
 */
readonly class CreateSubjectPageAction {

	public function __construct(
		private CreateSubjectPagePresenter $presenter,
		private SubjectRepository $subjectRepository,
		private NewSubjectIdResolver $newSubjectIdResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private PageIdentifiersResolver $pageIdentifiersResolver,
		private SchemaReferenceParser $schemaReferenceParser,
		private bool $validationEnforced,
		private SubjectNamer $subjectNamer,
	) {
	}

	public function createSubjectPage( CreateSubjectPageRequest $request ): void {
		$schemaReference = $this->schemaReferenceParser->localName( $request->schemaName );
		$schema = $this->schemaResolver->getSchema( $schemaReference );

		$titleAsked = $this->titleAsked( $request->pageTitle );
		$pageTitleAsked = $titleAsked === null ?
			null : $this->pageIdentifiersResolver->getMainNamespaceTitle( $titleAsked );

		if ( $titleAsked !== null && $pageTitleAsked === null ) {
			throw new InvalidPageTitleException( $titleAsked );
		}

		$subject = $this->buildSubject( $request, $schemaReference, $schema );
		$pageTitle = $pageTitleAsked ?? $this->pageTitleFor( $subject );

		// Authorized before the page is looked for, so that a title the caller may not write
		// answers the same whether or not a page holds it, rather than reporting one they may
		// not touch.
		if ( !$this->writeAuthorizer->authorizeCreatePage( $pageTitle ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to create this page' );
		}

		// Before the title check: a caller whose create already landed holds both the id and the
		// title it took, and the id is the answer that tells them their Subject exists.
		if ( $request->id !== null && $this->newSubjectIdResolver->isInUse( $subject->getId() ) ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
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
				$this->subjectNamer->chosenName( $subject, $pageSubjects, $pageTitle )
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
	 * The title the caller chose, or null where they chose none: nothing, empty and blank all
	 * leave the choice to the label.
	 */
	private function titleAsked( ?string $pageTitle ): ?string {
		$trimmed = trim( $pageTitle ?? '' );

		return $trimmed === '' ? null : $trimmed;
	}

	/**
	 * The page a Subject gets to itself where the caller chose no title: the page its label titles, else
	 * a free page its template label titles, and the page its own id titles when neither titles one.
	 */
	private function pageTitleFor( Subject $subject ): string {
		$label = $subject->getLabel()?->text;
		$subjectId = $subject->getId();
		$fromLabel = $label === null
			? $this->freeTitleFor( $this->subjectNamer->ownLabel( $subject ) )
			: $this->pageIdentifiersResolver->getMainNamespaceTitle( $label );

		// A Subject id titles a page whatever the label does: its grammar (ADR 14) holds none of
		// the characters MediaWiki refuses in a title. Normalized all the same, since a wiki that
		// capitalizes page titles - the default - stores it under an upper-case S.
		return $fromLabel
			?? $this->pageIdentifiersResolver->getMainNamespaceTitle( $subjectId->text )
			?? $subjectId->text;
	}

	/**
	 * Authorized before the page is looked for, so that whether a page holds a title the caller may not
	 * create stays unknown to them.
	 */
	private function freeTitleFor( ?string $templateLabel ): ?string {
		if ( $templateLabel === null ) {
			return null;
		}

		$title = $this->pageIdentifiersResolver->getMainNamespaceTitle( $templateLabel );

		if ( $title === null || !$this->writeAuthorizer->authorizeCreatePage( $title ) ) {
			return null;
		}

		return $this->pageIdentifiersResolver->getIdentifiersOfTitle( $title ) === null ? $title : null;
	}

	private function buildSubject(
		CreateSubjectPageRequest $request,
		SchemaReference $schemaReference,
		?Schema $schema
	): Subject {
		return new Subject(
			id: $this->newSubjectIdResolver->resolve( $request->id ),
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
