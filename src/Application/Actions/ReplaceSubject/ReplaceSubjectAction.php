<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\DependentSubjectBuilder;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\NeoWiki\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

readonly class ReplaceSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private DependentSubjectBuilder $dependentSubjectBuilder,
		private DependentSubjectValidator $dependentSubjectValidator,
		private SubjectResponseItemFactory $responseItemFactory,
		private ReplaceSubjectPresenter $presenter,
		private bool $validationEnforced,
	) {
	}

	/**
	 * Full replacement: a null label clears the stored one, exactly as an omitted property name
	 * deletes its Statement.
	 *
	 * @param array<string, mixed> $statements
	 *
	 * @throws InvalidArgumentException When the request holds a value or a Dependent Subject that cannot be built.
	 * @throws SubjectIdInUseException When a Dependent Subject takes an id another Subject holds.
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function replace(
		SubjectId $subjectId,
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

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		$pageSubjects = $dependentSubjects === [] ?
			PageSubjects::newEmpty() : $this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() );
		$newDependentSubjects = $this->dependentSubjectBuilder->build( $dependentSubjects, $subjectId, $pageSubjects );

		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$subject->setLabel( SubjectLabel::fromText( $label ) );
		$subject->setStatements(
			$this->statementListBuilder->build( $this->resolveStatements( $schema, $statements ) )
		);

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
			return;
		}

		try {
			$status = $this->subjectRepository->updateSubject( $subject, $comment, $newDependentSubjects );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			$this->presenter->presentValidationFailed( array_merge( $allViolations, $exception->violations ) );
			return;
		}

		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentSaveFailed( $status->errorMessage );
			return;
		}

		// The mutated Subject is the persisted state: the builder and the resolver above already
		// normalized what the request supplied.
		$this->presenter->presentUpdated(
			$this->newResponseItem( $subject, $pageIdentifiers ),
			$schema,
			$proposedViolations
		);
	}

	/**
	 * Which Subject the page treats as its own topic decides what a Subject without a label is called.
	 * Only the page knows that. A replace cannot change it, so reading it after the write is safe.
	 */
	private function newResponseItem( Subject $subject, PageIdentifiers $pageIdentifiers ): GetSubjectResponseItem {
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() );

		return $this->responseItemFactory->createResponseItem( $subject, $pageIdentifiers, $pageSubjects );
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
