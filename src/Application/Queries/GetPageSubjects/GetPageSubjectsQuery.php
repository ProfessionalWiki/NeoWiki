<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;

readonly class GetPageSubjectsQuery {

	public function __construct(
		private GetPageSubjectsPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private SubjectLookup $subjectLookup,
		private SchemaLookup $schemaLookup,
		private SchemaPresentationSerializer $schemaSerializer,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function execute( int $pageId, bool $includeSchemas = false, bool $includeReferencedSubjects = false ): void {
		$id = new PageId( $pageId );

		// A denied page takes exactly the path a page without Subjects takes, so the response
		// is byte-identical to absence and cannot be used to probe page readability (#1046).
		$pageSubjects = $this->readAuthorizer->authorizeReadByPageId( $id )
			? $this->subjectRepository->getSubjectsByPageId( $id )
			: PageSubjects::newEmpty();

		$mainSubject = $pageSubjects->getMainSubject();
		$subjectItems = [];

		if ( $mainSubject !== null ) {
			$subjectItems[$mainSubject->id->text] = $this->buildResponseItem(
				$mainSubject,
				$this->pageIdentifiersLookup->getPageIdOfSubject( $mainSubject->id )
			);
		}

		foreach ( $pageSubjects->getChildSubjects()->asArray() as $childSubject ) {
			$subjectItems[$childSubject->id->text] = $this->buildResponseItem(
				$childSubject,
				$this->pageIdentifiersLookup->getPageIdOfSubject( $childSubject->id )
			);
		}

		$referencedSubjectItems = null;
		if ( $includeReferencedSubjects ) {
			$referencedSubjectItems = $this->buildReferencedSubjectItems( $pageSubjects->getAllSubjects()->asArray(), $subjectItems );
		}

		$schemas = null;
		if ( $includeSchemas ) {
			$schemas = $this->buildSchemaMap( $subjectItems, $referencedSubjectItems );
		}

		$this->presenter->presentPageSubjects(
			new GetPageSubjectsResponse(
				pageId: $pageId,
				mainSubjectId: $mainSubject?->id->text,
				subjects: $subjectItems,
				referencedSubjects: $referencedSubjectItems,
				schemas: $schemas,
			)
		);
	}

	private function buildResponseItem( Subject $subject, ?PageIdentifiers $pageIdentifiers ): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaName: $subject->getSchemaName()->getText(),
			statements: $this->arrayifyStatements( $subject->getStatements() ),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
			pageNamespaceId: $pageIdentifiers?->getNamespaceId(),
		);
	}

	/**
	 * @param array<int, Subject> $pageSubjects
	 * @param array<string, GetSubjectResponseItem> $alreadyIncluded
	 * @return array<string, GetSubjectResponseItem>
	 */
	private function buildReferencedSubjectItems( array $pageSubjects, array $alreadyIncluded ): array {
		$referenced = [];

		foreach ( $pageSubjects as $subject ) {
			foreach ( $subject->getReferencedSubjects()->asArray() as $referencedId ) {
				if ( array_key_exists( $referencedId->text, $alreadyIncluded ) || array_key_exists( $referencedId->text, $referenced ) ) {
					continue;
				}

				$referencedSubject = $this->subjectLookup->getSubject( $referencedId );

				if ( $referencedSubject === null ) {
					continue;
				}

				$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $referencedSubject->id );

				if ( !$this->pageIsReadable( $pageIdentifiers ) ) {
					continue;
				}

				$referenced[$referencedId->text] = $this->buildResponseItem( $referencedSubject, $pageIdentifiers );
			}
		}

		return $referenced;
	}

	/**
	 * A Subject whose page does not resolve in the graph cannot be reached through the
	 * graph-backed repository at all (the repository returns null before this runs), so the
	 * null case is unreachable today. It fails closed regardless: if a SubjectLookup that
	 * bypasses the graph is ever wired into this query, unresolvable pages are omitted (this
	 * endpoint's normal absence shape) instead of served ungated. GetSubjectQuery's helper
	 * deliberately differs — its revision branch reaches null for Subjects from a revision
	 * the handler already authorized, which must stay readable.
	 */
	private function pageIsReadable( ?PageIdentifiers $pageIdentifiers ): bool {
		return $pageIdentifiers !== null && $this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() );
	}

	/**
	 * @param array<string, GetSubjectResponseItem> $pageSubjectItems
	 * @param array<string, GetSubjectResponseItem>|null $referencedSubjectItems
	 * @return array<string, string> Schema name → JSON-encoded schema
	 */
	private function buildSchemaMap( array $pageSubjectItems, ?array $referencedSubjectItems ): array {
		$schemaNames = [];
		foreach ( $pageSubjectItems as $item ) {
			$schemaNames[$item->schemaName] = true;
		}
		foreach ( $referencedSubjectItems ?? [] as $item ) {
			$schemaNames[$item->schemaName] = true;
		}

		$schemas = [];
		foreach ( array_keys( $schemaNames ) as $schemaName ) {
			$schema = $this->schemaLookup->getSchema( new SchemaName( $schemaName ) );

			if ( $schema !== null ) {
				$schemas[$schemaName] = $this->schemaSerializer->serialize( $schema );
			}
		}

		return $schemas;
	}

	/**
	 * @return array<string, mixed>
	 */
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
