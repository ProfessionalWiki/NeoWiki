<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\DependentSubjectBuilder;
use ProfessionalWiki\NeoWiki\Application\EditConflictCheck;
use ProfessionalWiki\NeoWiki\Application\SubjectAtRevision;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\NeoWiki\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMerge;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

readonly class ReplaceSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private DependentSubjectBuilder $dependentSubjectBuilder,
		private DependentSubjectValidator $dependentSubjectValidator,
		private DependentSubjectRules $dependentSubjectRules,
		private EditConflictCheck $editConflictCheck,
		private SubjectResponseItemFactory $responseItemFactory,
		private ReplaceSubjectPresenter $presenter,
		private bool $validationEnforced,
	) {
	}

	/**
	 * Full replacement: a null label clears the stored one, exactly as an omitted property name
	 * deletes its Statement.
	 *
	 * @param int $baseRevisionId The revision the request was made from. What changed after it is kept beside the
	 *  write, which is refused when it changes the same label, Statement or Dependent Subject.
	 * @param array<string, mixed> $statements
	 *
	 * @throws InvalidArgumentException When the request holds a value or a Dependent Subject that cannot be built.
	 * @throws SubjectIdInUseException When a Dependent Subject takes an id another Subject holds.
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function replace(
		SubjectId $subjectId,
		int $baseRevisionId,
		?string $label,
		array $statements,
		?string $comment,
		array $dependentSubjects = []
	): void {
		$pageIdentifiers = $this->hostingPageResolver->resolveReadableHostingPage( $subjectId );

		if ( $pageIdentifiers === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		if ( !$this->writeAuthorizer->authorize( $pageIdentifiers->getId() ) ) {
			throw new SubjectEditNotAuthorizedException();
		}

		$request = new ReplaceSubjectRequest( $subjectId, $baseRevisionId, $label, $statements, $comment, $dependentSubjects );

		if ( !$this->editConflictCheck->attemptUntilLanded( fn (): bool => $this->replaceOnLatestRevision( $request, $pageIdentifiers ) ) ) {
			$this->presenter->presentSaveFailed( EditConflictCheck::PAGE_KEPT_CHANGING );
		}
	}

	/**
	 * @return bool False when another save landed after the read, so nothing was written or presented.
	 *
	 * @throws InvalidArgumentException
	 * @throws SubjectIdInUseException
	 * @throws SubjectNotFoundException
	 */
	private function replaceOnLatestRevision( ReplaceSubjectRequest $request, PageIdentifiers $pageIdentifiers ): bool {
		$latest = $this->editConflictCheck->readLatest( $pageIdentifiers->getId(), $request->subjectId );
		$pageSubjects = $latest->pageSubjects;

		$merged = $this->mergedWithChangesSinceBase( $request, $latest, $pageIdentifiers );

		if ( $merged === null ) {
			$this->presenter->presentConflict(
				$this->responseItemFactory->createResponseItem( $latest->subject, $pageIdentifiers, $pageSubjects ),
				$latest->revisionId
			);
			return true;
		}

		$subject = $merged->getSubject( $request->subjectId ) ?? throw SubjectNotFoundException::forId( $request->subjectId );
		$newDependentSubjects = $merged->without( $request->subjectId );

		$priorViolations = $this->proposedSubjectValidator->validate( $latest->subject );

		$proposedViolations = $this->proposedSubjectValidator->validate( $subject, $newDependentSubjects );
		$dependentSubjectViolations = $this->dependentSubjectValidator->validate(
			$newDependentSubjects,
			$subject,
			$pageSubjects->getAllSubjects()->onlyWithIds( $newDependentSubjects->getIds() )
		);
		$allViolations = array_merge( $proposedViolations, $dependentSubjectViolations->all );

		// Only violations this edit introduces block it, so a Subject that already carries one stays
		// editable rather than being frozen by it.
		if ( $this->validationEnforced
			&& $dependentSubjectViolations->blockTheWriteWith( ViolationDiff::newViolations( $proposedViolations, $priorViolations ) )
		) {
			$this->presenter->presentValidationFailed( $allViolations );
			return true;
		}

		try {
			$status = $this->subjectRepository->updateSubject( $subject, $latest->revisionId, $request->comment, $newDependentSubjects );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			$this->presenter->presentValidationFailed( array_merge( $allViolations, $exception->violations ) );
			return true;
		}

		if ( $status->status === PageContentSavingStatus::CONFLICT ) {
			return false;
		}

		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentSaveFailed( $status->errorMessage );
			return true;
		}

		// The merged Subject is the persisted state: the builders already normalized what the request
		// supplied. Which Subject the page treats as its own topic decides what a Subject
		// without a label is called, and a replace cannot change it.
		$responseItem = $this->responseItemFactory->createResponseItem( $subject, $pageIdentifiers, $pageSubjects );
		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		if ( $status->status === PageContentSavingStatus::NO_CHANGES ) {
			$this->presenter->presentNoChange( $responseItem, $schema, $proposedViolations, $latest->revisionId );
			return true;
		}

		$this->presenter->presentUpdated( $responseItem, $schema, $proposedViolations, $status->revisionId ?? $latest->revisionId );
		return true;
	}

	/**
	 * The Subject and its Dependent Subjects as the write sets them, with what changed since its base revision.
	 *
	 * @return SubjectMap|null Null when the write and those changes set the same field differently, or the base
	 *  revision does not hold the Subject.
	 *
	 * @throws InvalidArgumentException
	 * @throws SubjectIdInUseException
	 */
	private function mergedWithChangesSinceBase(
		ReplaceSubjectRequest $request,
		SubjectAtRevision $latest,
		PageIdentifiers $pageIdentifiers
	): ?SubjectMap {
		$subject = new Subject(
			id: $request->subjectId,
			label: SubjectLabel::fromText( $request->label ),
			schema: $latest->subject->getSchemaReference(),
			statements: $this->statementListBuilder->build( $request->statements ),
		);
		$sentDependentSubjects = $this->dependentSubjectBuilder->build(
			$request->dependentSubjects,
			$request->subjectId,
			$latest->pageSubjects
		);

		if ( $request->baseRevisionId === $latest->revisionId ) {
			return $sentDependentSubjects->prepend( $subject );
		}

		$base = $this->editConflictCheck->readAt( $request->baseRevisionId, $pageIdentifiers->getId(), $request->subjectId );

		if ( $base === null ) {
			return null;
		}

		// A write sends only the Dependent Subjects it changed: one it leaves out but still reaches is as it was at
		// the base revision. One it sends stays even unreached, so the write is refused for it as without a merge.
		return SubjectMerge::merge(
			$this->withDependentSubjects( $base->subject, $base->pageSubjects ),
			$this->withDependentSubjects( $latest->subject, $latest->pageSubjects ),
			$this->withDependentSubjects(
				$subject,
				new PageSubjects( null, $base->pageSubjects->getAllSubjects()->union( $sentDependentSubjects ) )
			)->union( $sentDependentSubjects )
		);
	}

	private function withDependentSubjects( Subject $subject, PageSubjects $pageSubjects ): SubjectMap {
		return $this->dependentSubjectRules->dependentSubjectsReachedFrom( $subject, $pageSubjects )->prepend( $subject );
	}

}
