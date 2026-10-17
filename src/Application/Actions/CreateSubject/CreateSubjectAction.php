<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\DependentSubjectBuilder;
use ProfessionalWiki\NeoWiki\Application\NewSubjectIdResolver;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\NeoWiki\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

readonly class CreateSubjectAction {

	public function __construct(
		private CreateSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private NewSubjectIdResolver $newSubjectIdResolver,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private PageIdentifiersResolver $pageIdentifiersResolver,
		private SchemaReferenceParser $schemaReferenceParser,
		private DependentSubjectBuilder $dependentSubjectBuilder,
		private DependentSubjectValidator $dependentSubjectValidator,
		private bool $validationEnforced,
	) {
	}

	/**
	 * @throws InvalidArgumentException When the request holds a value or a Dependent Subject that cannot be built.
	 * @throws SubjectIdInUseException When a Dependent Subject takes an id another Subject holds.
	 */
	public function createSubject( CreateSubjectRequest $request ): void {
		$pageId = new PageId( $request->pageId );

		// Gate on read before write, and before touching any page state: a page the caller may not
		// read, and a page that does not exist, both answer the same not-found shape, so restricted
		// pages cannot be told apart from absent ones by sweeping page ids. Only a page the caller
		// can read (its existence already public) proceeds to the write check and its 403.
		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageId ) ) {
			$this->presenter->presentPageNotFound();
			return;
		}

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to create this subject' );
		}

		$schemaReference = $this->schemaReference( $request );
		$schema = $this->schemaResolver->getSchema( $schemaReference );

		$subject = $this->buildSubject( $request, $schemaReference );

		if ( $request->id !== null && $this->newSubjectIdResolver->isInUse( $subject->id ) ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageId );
		$dependentSubjects = $this->dependentSubjectBuilder->build( $request->dependentSubjects, $subject->id, $pageSubjects );
		$storedDependentSubjects = $pageSubjects->getAllSubjects()->onlyWithIds( $dependentSubjects->getIds() );

		if ( !$this->addToPage( $pageSubjects, $subject, $dependentSubjects, $request->isMainSubject ) ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$violations = $this->proposedSubjectValidator->validate( $subject, $dependentSubjects );
		$dependentSubjectViolations = $this->dependentSubjectValidator->validate( $dependentSubjects, $subject, $storedDependentSubjects );
		$allViolations = array_merge( $violations, $dependentSubjectViolations->all );

		if ( $this->validationEnforced && $dependentSubjectViolations->blockTheWriteWith( $violations ) ) {
			$this->presenter->presentValidationFailed( $allViolations );
			return;
		}

		try {
			$status = $this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			$this->presenter->presentValidationFailed( array_merge( $allViolations, $exception->violations ) );
			return;
		}

		// The read gate above already turns an unresolvable page away; this catches the page going
		// away between that check and the save, so a dropped write is never reported as created.
		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentPageNotFound();
			return;
		}

		// The page identifiers come from the page id the request named, not from the subject -> page
		// index, which is read from a replica that may not carry the revision just written.
		$pageIdentifiers = $this->pageIdentifiersResolver->getIdentifiersOfPage( $pageId );

		$pageName = $pageIdentifiers?->getTitle() ?? '';

		$this->presenter->presentCreated(
			GetSubjectResponseItem::fromSubject(
				$subject,
				$pageIdentifiers,
				SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageName ),
				hostSubject: null
			),
			$schema,
			$violations
		);
	}

	/**
	 * False, adding nothing, when the page already holds $subject's id, or already has a Main Subject and
	 * $subject is to be one.
	 */
	private function addToPage( PageSubjects $pageSubjects, Subject $subject, SubjectMap $dependentSubjects, bool $isMainSubject ): bool {
		try {
			if ( $isMainSubject ) {
				$pageSubjects->createMainSubject( $subject );
			} else {
				$pageSubjects->createOtherSubject( $subject );
			}
		} catch ( RuntimeException ) {
			return false;
		}

		foreach ( $dependentSubjects->asArray() as $dependentSubject ) {
			$pageSubjects->putSubject( $dependentSubject );
		}

		return true;
	}

	private function buildSubject( CreateSubjectRequest $request, SchemaReference $schemaReference ): Subject {
		return new Subject(
			id: $this->newSubjectIdResolver->resolve( $request->id ),
			label: SubjectLabel::fromText( $request->label ),
			schema: $schemaReference,
			statements: $this->statementListBuilder->build( $request->statements ),
		);
	}

	/**
	 * A Subject is only ever created in the local Source, so the Schema it names is a local one too.
	 */
	private function schemaReference( CreateSubjectRequest $request ): SchemaReference {
		return $this->schemaReferenceParser->localName( $request->schemaName );
	}

}
