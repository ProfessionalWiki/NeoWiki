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
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
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
			$subjectItems[$mainSubject->id->text] = GetSubjectResponseItem::fromSubject(
				$mainSubject,
				$this->pageIdentifiersLookup->getPageIdOfSubject( $mainSubject->id )
			);
		}

		foreach ( $pageSubjects->getChildSubjects()->asArray() as $childSubject ) {
			$subjectItems[$childSubject->id->text] = GetSubjectResponseItem::fromSubject(
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

				// An unresolvable page is omitted rather than served ungated. The graph-backed
				// repository cannot reach one (it returns null first), so this only guards a
				// future SubjectLookup that bypasses the graph.
				if ( $pageIdentifiers === null
					|| !$this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() ) ) {
					continue;
				}

				$referenced[$referencedId->text] = GetSubjectResponseItem::fromSubject( $referencedSubject, $pageIdentifiers );
			}
		}

		return $referenced;
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

}
