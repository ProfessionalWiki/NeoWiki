<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class GetSubjectQuery {

	public function __construct(
		private readonly GetSubjectPresenter $presenter,
		private readonly SubjectLookup $subjectLookup,
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	public function execute( string $subjectId, bool $includePageIdentifiers ): void {
		$subject = $this->subjectLookup->getSubject( new SubjectId( $subjectId ) );

		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$this->presenter->presentSubject( $this->createResponse(
			subject: $subject,
			pageIdentifiers: $includePageIdentifiers ? $this->pageIdentifiersLookup->getPageIdOfSubject( $subject->id ) : null
		) );
	}

	private function createResponse( Subject $subject, ?PageIdentifiers $pageIdentifiers ): GetSubjectResponse {
		return new GetSubjectResponse(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaId: $subject->getSchemaId()->getText(),
			properties: $subject->getProperties()->asMap() + $subject->getRelations()->asMap(),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
		);
	}

}
