<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class GetSubjectQuery {

	public function __construct(
		private GetSubjectPresenter $presenter,
		private SubjectLookup $subjectLookup,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private SubjectReadAuthorizer $readAuthorizer,
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

		if ( !$this->pageIsReadable( $pageIdentifiers ) ) {
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

				if ( !$this->pageIsReadable( $referencedPageIdentifiers ) ) {
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
	 * A Subject whose page does not resolve in the graph can only have come from a revision
	 * the caller supplied, and GetSubjectApi authorizes read on that revision's page before
	 * constructing this query. Reads through the graph-backed repository always resolve the
	 * owning page (the repository returns null otherwise), so null never bypasses the gate
	 * there. Denying on null would instead hide Subjects from readable old revisions after
	 * the Subject was later deleted.
	 */
	private function pageIsReadable( ?PageIdentifiers $pageIdentifiers ): bool {
		return $pageIdentifiers === null || $this->readAuthorizer->authorizeRead( $pageIdentifiers->getId() );
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
