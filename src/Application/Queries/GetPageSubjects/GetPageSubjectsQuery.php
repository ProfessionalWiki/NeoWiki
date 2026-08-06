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
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
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
		$subjectsOnPage = $pageSubjects->getAllSubjects();

		// Each Subject takes its own entry rather than the page's: these all came out of one page's
		// content, but an unrebuilt or stale graph can still place two of them on different pages.
		$hostingPages = $this->pageIdentifiersLookup->getPageIdsOfSubjects( $subjectsOnPage->getIds() );

		$subjectItems = [];

		if ( $mainSubject !== null ) {
			$subjectItems[$mainSubject->id->text] = GetSubjectResponseItem::fromSubject(
				$mainSubject,
				$hostingPages[$mainSubject->id->text] ?? null
			);
		}

		foreach ( $pageSubjects->getChildSubjects()->asArray() as $childSubject ) {
			$subjectItems[$childSubject->id->text] = GetSubjectResponseItem::fromSubject(
				$childSubject,
				$hostingPages[$childSubject->id->text] ?? null
			);
		}

		$referencedSubjectItems = null;
		if ( $includeReferencedSubjects ) {
			$referencedSubjectItems = $this->buildReferencedSubjectItems( $subjectsOnPage->asArray(), $subjectItems );
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
		$referencedIds = $this->collectReferencedIds( $pageSubjects, $alreadyIncluded );

		$referencedSubjects = $this->subjectLookup->getSubjects( $referencedIds );
		$hostingPages = $this->pageIdentifiersLookup->getPageIdsOfSubjects( $referencedIds );

		$referenced = [];

		// Iterated by the collected ids, not the returned map: the response keeps the order the
		// Statements reach the targets, and SubjectMap promises no order of its own.
		foreach ( $referencedIds->asArray() as $idText => $referencedId ) {
			$referencedSubject = $referencedSubjects->getSubject( $referencedId );

			if ( $referencedSubject === null ) {
				continue;
			}

			$pageIdentifiers = $hostingPages[$idText] ?? null;

			// An unresolvable page is omitted rather than served ungated. The graph-backed
			// repository cannot reach one (it returns null first), so this only guards a
			// future SubjectLookup that bypasses the graph.
			if ( $pageIdentifiers === null
				|| !$this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() ) ) {
				continue;
			}

			$referenced[$idText] = GetSubjectResponseItem::fromSubject( $referencedSubject, $pageIdentifiers );
		}

		return $referenced;
	}

	/**
	 * The distinct Subjects the page's Statements reach that the page does not itself carry.
	 * SubjectIdList deduplicates, so a target reached from two Statements is resolved once.
	 *
	 * @param array<int, Subject> $pageSubjects
	 * @param array<string, GetSubjectResponseItem> $alreadyIncluded
	 */
	private function collectReferencedIds( array $pageSubjects, array $alreadyIncluded ): SubjectIdList {
		$ids = [];

		foreach ( $pageSubjects as $subject ) {
			foreach ( $subject->getReferencedSubjects()->asArray() as $idText => $referencedId ) {
				if ( !array_key_exists( $idText, $alreadyIncluded ) ) {
					$ids[] = $referencedId;
				}
			}
		}

		return new SubjectIdList( $ids );
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
