<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\NewSubjectIdResolver;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
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
		private SelectStatementResolver $selectStatementResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private PageIdentifiersResolver $pageIdentifiersResolver,
		private bool $validationEnforced,
	) {
	}

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

		$schema = $this->schemaResolver->getSchema( $this->schemaReference( $request ) );

		$subject = $this->buildSubject( $request, $schema );

		if ( $request->id !== null && $this->newSubjectIdResolver->isInUse( $subject->id ) ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageId );

		try {
			if ( $request->isMainSubject ) {
				$pageSubjects->createMainSubject( $subject );
			} else {
				$pageSubjects->createOtherSubject( $subject );
			}
		} catch ( RuntimeException ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$violations = $this->proposedSubjectValidator->validate( $subject );

		if ( $this->validationEnforced && $this->blockingViolations( $violations ) !== [] ) {
			$this->presenter->presentValidationFailed( $violations );
			return;
		}

		$status = $this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );

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
				SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageName )
			),
			$schema,
			$violations
		);
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

	private function buildSubject( CreateSubjectRequest $request, ?Schema $schema ): Subject {
		return new Subject(
			id: $this->newSubjectIdResolver->resolve( $request->id ),
			label: SubjectLabel::fromText( $request->label ),
			schema: $this->schemaReference( $request ),
			statements: $this->statementListBuilder->build(
				$this->resolveSelectValues( $schema, $request->statements )
			),
		);
	}

	/**
	 * A Subject is only ever created in the local Source, so the Schema it names is a local one too.
	 */
	private function schemaReference( CreateSubjectRequest $request ): SchemaReference {
		return SchemaReference::local( new SchemaName( $request->schemaName ) );
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

}
