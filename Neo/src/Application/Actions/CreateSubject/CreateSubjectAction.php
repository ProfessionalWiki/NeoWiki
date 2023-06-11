<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use RuntimeException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;

class CreateSubjectAction {

	public function __construct(
		private readonly CreateSubjectPresenter $presenter,
		private readonly SubjectRepository $subjectRepository,
		private readonly GuidGenerator $guidGenerator,
	) {
	}

	public function createSubject( CreateSubjectRequest $request ): void {
		$subject = $this->buildSubject( $request );

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( new PageId( $request->pageId ) );

		try {
			if ( $request->isMainSubject ) {
				$pageSubjects->createMainSubject( $subject );
			} else {
				$pageSubjects->createChildSubject( $subject );
			}
		} catch ( RuntimeException $e ) {
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
			schemaId: new SchemaId( $request->schemaId ),
			properties: $this->buildSubjectProperties( $request ),
			// TODO: relations
		);
	}

	private function buildSubjectProperties( CreateSubjectRequest $request ): StatementList {
		return new StatementList( $request->properties );
	}

}
