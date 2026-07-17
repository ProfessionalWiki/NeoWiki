<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class GetSubjectQuery {

	public function __construct(
		private GetSubjectPresenter $presenter,
		private SubjectLookup $subjectLookup,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private PageReadAuthorizer $readAuthorizer,
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

		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subject->id );

		if ( !$this->pageIsReadableOrUnresolved( $pageIdentifiers ) ) {
			// Denial takes exactly the absent-Subject path, so harvested Subject ids cannot
			// be confirmed to exist on restricted pages (#1046).
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$response = [
			$subject->getId()->text => $this->createResponse( $subject, $includePageIdentifiers, $pageIdentifiers )
		];

		if ( $includeReferencedSubjects ) {
			foreach ( $subject->getReferencedSubjects()->asArray() as $id ) {
				$referencedSubject = $this->subjectLookup->getSubject( $id );

				if ( $referencedSubject === null ) {
					continue;
				}

				$referencedPageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $referencedSubject->id );

				if ( !$this->pageIsReadableOrUnresolved( $referencedPageIdentifiers ) ) {
					continue;
				}

				$response[$referencedSubject->getId()->text] =
					$this->createResponse( $referencedSubject, $includePageIdentifiers, $referencedPageIdentifiers );
			}
		}

		$this->presenter->presentSubject(
			new GetSubjectResponse(
				requestedId: $subject->getId()->text,
				subjects: $response
			)
		);
	}

	/**
	 * Unresolved is allowed because it means the Subject came from the revision the caller
	 * supplied, whose page GetSubjectApi already authorized: reads through the graph-backed
	 * repository always resolve the owning page. Denying would hide Subjects from readable old
	 * revisions after the Subject was later deleted.
	 */
	private function pageIsReadableOrUnresolved( ?PageIdentifiers $pageIdentifiers ): bool {
		return $pageIdentifiers === null || $this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() );
	}

	private function createResponse(
		Subject $subject,
		bool $includePageIdentifiers,
		?PageIdentifiers $pageIdentifiers
	): GetSubjectResponseItem {
		$includedIdentifiers = $includePageIdentifiers ? $pageIdentifiers : null;

		return new GetSubjectResponseItem(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaName: $subject->getSchemaName()->getText(),
			statements: $this->arrayifyStatements( $subject->getStatements() ),
			pageId: $includedIdentifiers?->getId()->id,
			pageTitle: $includedIdentifiers?->getTitle(),
			pageNamespaceId: $includedIdentifiers?->getNamespaceId(),
		);
	}

	private function arrayifyStatements( StatementList $statements ): array {
		$array = [];

		foreach ( $statements->asArray() as $statement ) {
			$array[$statement->getPropertyName()->text] = [
				'type' => $statement->getPropertyType(),
				'value' => $statement->getValue()->toScalars()
			];
		}

		return $array;
	}

}
