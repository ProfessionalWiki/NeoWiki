<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeIdList;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class CreateSubjectAction {

	public function __construct(
		private readonly CreateSubjectPresenter $presenter,
		private readonly SubjectRepository $subjectRepository,
		private readonly GuidGenerator $guidGenerator,
	) {
	}

	public function createSubject( CreateSubjectRequest $request ): void {
		$subject = $this->buildSubject( $request );

		$this->subjectRepository->createSubject(
			$subject,
			new PageId( $request->pageId )
		);

		$this->presenter->presentCreated( $subject->id->text );
	}

	private function buildSubject( CreateSubjectRequest $request ): Subject {
		return Subject::createNew(
			guidGenerator: $this->guidGenerator,
			label: new SubjectLabel( $request->label ),
			types: $this->buildSubjectTypes( $request ),
			properties: $this->buildSubjectProperties( $request ),
			// TODO: relations
		);
	}

	private function buildSubjectTypes( CreateSubjectRequest $request ): SubjectTypeIdList {
		return new SubjectTypeIdList( array_map(
			fn( string $type ) => new SubjectTypeId( $type ),
			$request->types
		) );
	}

	private function buildSubjectProperties( CreateSubjectRequest $request ): SubjectProperties {
		return new SubjectProperties( $request->properties );
	}

}
