<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
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
	) {
	}

	public function execute( int $pageId, bool $includeSchemas = false, bool $includeReferencedSubjects = false ): void {
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( new PageId( $pageId ) );

		$mainSubject = $pageSubjects->getMainSubject();
		$subjectItems = [];

		if ( $mainSubject !== null ) {
			$subjectItems[$mainSubject->id->text] = $this->buildResponseItem( $mainSubject );
		}

		foreach ( $pageSubjects->getChildSubjects()->asArray() as $childSubject ) {
			$subjectItems[$childSubject->id->text] = $this->buildResponseItem( $childSubject );
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

	private function buildResponseItem( Subject $subject ): GetSubjectResponseItem {
		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subject->id );

		return new GetSubjectResponseItem(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaName: $subject->getSchemaName()->getText(),
			statements: $this->arrayifyStatements( $subject->getStatements() ),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
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

				if ( $referencedSubject !== null ) {
					$referenced[$referencedId->text] = $this->buildResponseItem( $referencedSubject );
				}
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
