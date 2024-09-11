<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use RuntimeException;

class CreateSubjectAction {

	public function __construct(
		private readonly CreateSubjectPresenter $presenter,
		private readonly SubjectRepository $subjectRepository,
		private readonly GuidGenerator $guidGenerator,
		private readonly SubjectAuthorizer $subjectActionAuthorizer,
		private readonly StatementListPatcher $statementListPatcher,
	) {
	}

	public function createSubject( CreateSubjectRequest $request ): void {
		if ( ( $request->isMainSubject && !$this->subjectActionAuthorizer->canCreateMainSubject(
				) ) || !$this->subjectActionAuthorizer->canCreateChildSubject() ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to create this subject' );
		}

		$subject = $this->buildSubject( $request );

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( new PageId( $request->pageId ) );

		try {
			if ( $request->isMainSubject ) {
				$pageSubjects->createMainSubject( $subject );
			} else {
				$pageSubjects->createChildSubject( $subject );
			}
		}
		catch ( RuntimeException ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$this->subjectRepository->savePageSubjects( $pageSubjects, new PageId( $request->pageId ) );
		$this->presenter->presentCreated( $subject->id->text );
	}

	private function buildSubject( CreateSubjectRequest $request ): Subject {
		return Subject::createNew(
			guidGenerator: $this->guidGenerator,
			label: new SubjectLabel( $request->label ),
			schemaId: new SchemaName( $request->schemaId ),
			statements: $this->statementListPatcher->buildStatementList(
				statements: new StatementList(),
				patch: $request->statements
			)
		);
	}

}
