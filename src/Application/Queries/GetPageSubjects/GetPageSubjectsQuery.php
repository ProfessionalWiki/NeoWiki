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
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
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
			$subjectItems[$mainSubject->id->text] = $this->buildItem(
				$mainSubject,
				$hostingPages[$mainSubject->id->text] ?? null,
				isMainSubject: true
			);
		}

		foreach ( $pageSubjects->getOtherSubjects()->asArray() as $otherSubject ) {
			$subjectItems[$otherSubject->id->text] = $this->buildItem(
				$otherSubject,
				$hostingPages[$otherSubject->id->text] ?? null,
				isMainSubject: false
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
		/** @var array<int, ?SubjectId> $mainSubjectIds */
		$mainSubjectIds = [];

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

			$referenced[$idText] = $this->buildItem(
				$referencedSubject,
				$pageIdentifiers,
				isMainSubject: $pageIdentifiers !== null
					&& $this->isMainSubjectOfItsPage( $referencedSubject, $pageIdentifiers, $mainSubjectIds )
			);
		}

		return $referenced;
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
	 * A target lives on a page of its own, so whether it is that page's Main Subject has to be asked
	 * rather than known: one page read per distinct target page, and none for a target whose stored
	 * label makes the question moot.
	 *
	 * @param array<int, ?SubjectId> $mainSubjectIds Page ID → that page's Main Subject, filled as pages
	 *   are reached so that several targets on one page cost one read.
	 */
	private function isMainSubjectOfItsPage(
		Subject $subject,
		PageIdentifiers $pageIdentifiers,
		array &$mainSubjectIds
	): bool {
		$pageId = $pageIdentifiers->getId();

		if ( $subject->getLabel() === null && !array_key_exists( $pageId->id, $mainSubjectIds ) ) {
			$mainSubjectIds[$pageId->id] = $this->subjectRepository
				->getSubjectsByPageId( $pageId )
				->getMainSubject()
				?->getId();
		}

		return ( $mainSubjectIds[$pageId->id] ?? null )?->equals( $subject->getId() ) ?? false;
	}

	private function buildItem(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		bool $isMainSubject
	): GetSubjectResponseItem {
		return GetSubjectResponseItem::fromSubject(
			$subject,
			$pageIdentifiers,
			SubjectDisplayName::labelOrPageName(
				label: $subject->getLabel(),
				isMainSubject: $isMainSubject,
				pageName: $pageIdentifiers?->getTitle() ?? ''
			)
		);
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
