<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class GetSubjectQuery {

	public function __construct(
		private readonly GetSubjectPresenter $presenter,
		private readonly SubjectRepository $subjectRepository
	) {
	}

	public function execute( string $subjectId ): void {
		$subject = $this->subjectRepository->getSubject( new SubjectId( $subjectId ) );

		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$this->presenter->presentSubject( $this->createResponse( $subject ) );
	}

	private function createResponse( Subject $subject ): GetSubjectResponse {
		return new GetSubjectResponse(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaId: $subject->getSchemaId()->getText(),
			properties: $subject->getProperties()->asMap() + $subject->getRelations()->asMap()
			// TODO: should relations get a dedicated value?
		);
	}

}
