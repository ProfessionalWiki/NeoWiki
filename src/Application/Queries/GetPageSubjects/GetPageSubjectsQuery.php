<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;

readonly class GetPageSubjectsQuery {

	public function __construct(
		private GetPageSubjectsPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private SubjectResponseItemFactory $responseItemFactory,
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

		$subjectsOnPage = $pageSubjects->getAllSubjects();

		// Each Subject takes its own entry rather than the page's: these all came out of one page's
		// content, but an unrebuilt or stale graph can still place two of them on different pages.
		$hostingPages = $this->pageIdentifiersLookup->getPageIdsOfSubjects( $subjectsOnPage->getIds() );

		$subjectItems = [];

		foreach ( $subjectsOnPage->asArray() as $subject ) {
			$pageIdentifiers = $hostingPages[$subject->id->text] ?? null;

			$subjectItems[$subject->id->text] = GetSubjectResponseItem::fromSubject(
				$subject,
				$pageIdentifiers,
				SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageIdentifiers?->getTitle() ?? '' )
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
				mainSubjectId: $pageSubjects->getMainSubject()?->id->text,
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

		$placedSubjects = [];

		// Iterated by the collected ids, not the returned map: the response keeps the order the
		// Statements reach the targets, and SubjectMap promises no order of its own.
		foreach ( $referencedIds->asArray() as $idText => $referencedId ) {
			$referencedSubject = $referencedSubjects->getSubject( $referencedId );

			if ( $referencedSubject === null ) {
				continue;
			}

			$pageIdentifiers = $hostingPages[$idText] ?? null;

			if ( !$this->mayServe( $referencedId, $pageIdentifiers ) ) {
				continue;
			}

			$placedSubjects[$idText] = [ $referencedSubject, $pageIdentifiers ];
		}

		return $this->responseItemFactory->createResponseItems( $placedSubjects, includePageIdentifiers: true );
	}

	/**
	 * A local target with no resolvable page is omitted rather than served ungated: nothing authorizes
	 * it. A sourced target has no page of this wiki to resolve, and its Source vouched for it
	 * (ADR 23), so it is served without one.
	 */
	private function mayServe( SubjectId $id, ?PageIdentifiers $pageIdentifiers ): bool {
		if ( !$id->isLocal() ) {
			return true;
		}

		return $pageIdentifiers !== null
			&& $this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() );
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
		// The map is keyed by Schema name and served from this wiki's Schema pages, so a Subject whose
		// Schema comes from another Source contributes nothing to it: there is no local name to key it
		// under. Consumers read that Subject's Schema reference and resolve it themselves.
		$schemaNames = [];
		foreach ( array_merge( $pageSubjectItems, $referencedSubjectItems ?? [] ) as $item ) {
			if ( is_string( $item->schema ) ) {
				$schemaNames[$item->schema] = true;
			}
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
