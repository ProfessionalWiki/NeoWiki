<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class GetSubjectQuery {

	public function __construct(
		private GetSubjectPresenter $presenter,
		private SubjectLookup $subjectLookup,
		private PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	public function execute(
		string $subjectId,
		bool $includePageIdentifiers,
		bool $includeReferencedSubjects
	): void {
		$subject = $this->subjectLookup->getSubject( new SubjectId( $subjectId ) ); // TODO: error handling on invalid ID

		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$response = [
			$subject->getId()->text => $this->createResponse( $subject, $includePageIdentifiers )
		];

		if ( $includeReferencedSubjects ) {
			foreach ( $subject->getReferencedSubjects()->asArray() as $id ) {
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
			statements: $this->arrayifyStatements( $subject->getStatements() ),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
		);
	}

	private function arrayifyStatements( StatementList $statements ): array {
		$array = [];

		foreach ( $statements->asArray() as $statement ) {
			$array[$statement->getPropertyName()->text] = [
				'type' => $statement->getFormat(),
				'value' => $statement->getValue()->toScalars()
			];
		}

		return $array;
	}

}
