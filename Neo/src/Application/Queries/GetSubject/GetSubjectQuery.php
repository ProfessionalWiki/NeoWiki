<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class GetSubjectQuery {

	public function __construct(
		private readonly GetSubjectPresenter $presenter,
		private readonly SubjectLookup $subjectLookup,
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	public function execute(
		string $subjectId,
		bool $includePageIdentifiers,
		bool $includeReferencedSubjects
	): void {
		$subject = $this->subjectLookup->getSubject( new SubjectId( $subjectId ) );

		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$response = [
			$subject->getId()->text => $this->createResponse( $subject, $includePageIdentifiers )
		];

		if ( $includeReferencedSubjects ) {
			foreach ( $subject->getReferencedSubjects() as $id ) {
				$referencedSubject = $this->subjectLookup->getSubject( $id );

				if ( $referencedSubject !== null ) {
					$response[$referencedSubject->getId()->text] = $this->createResponse( $referencedSubject, $includePageIdentifiers );
				}
			}
		}

		$this->presenter->presentSubject(
			new GetSubjectResponse(
				requestedId: $subject->getId()->text,
				subjects: $response
			)
		);
	}

	private function createResponse( Subject $subject, bool $includePageIdentifiers ): GetSubjectResponseItem {
		$pageIdentifiers = $includePageIdentifiers ? $this->pageIdentifiersLookup->getPageIdOfSubject( $subject->id ) : null;

		return new GetSubjectResponseItem(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaId: $subject->getSchemaId()->getText(),
			properties: $subject->getProperties()->asMap() + $subject->getRelations()->asMap(),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
		);
	}

}
