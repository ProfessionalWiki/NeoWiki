<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use MediaWiki\Revision\SlotRecord;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\RevisionUpdater;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use RuntimeException;
use Content;
use WikitextContent;

class CreateSubjectsAction {

	/**
	 * Subject Ids to overwrite
	 * array key is an old subject id
	 * array value is a new one
	 * @var array<string, string>
	 */
	private array $subjectIds = [];

	public function __construct(
		private readonly SubjectRepository $subjectRepository,
		private readonly GuidGenerator $guidGenerator,
		private readonly SubjectActionAuthorizer $subjectActionAuthorizer,
		private readonly StatementListPatcher $statementListPatcher,
		private readonly RevisionUpdater $revisionUpdater
	) {
	}

	public function createSubjects( CreateSubjectsRequest $request ): void {
		if ( !$this->subjectActionAuthorizer->canCreateChildSubject() ) {
			throw new RuntimeException( 'You do not have the necessary permissions to create this subject' );
		}

		$subjects = $this->buildSubjects( $request );
		if ( empty( $subjects ) ) {
			return;
		}

		$this->revisionUpdater->addSubjectsToRevision( [
			MediaWikiSubjectRepository::SLOT_NAME => $this->getSubjectsContent( $subjects, $request ),
			SlotRecord::MAIN => $this->getMainContent( $request )
		] );
	}

	/**
	 * @param array<int, Subject> $subjects
	 */
	private function getSubjectsContent( array $subjects, CreateSubjectsRequest $request ): SubjectContent {
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $request->pageId );

		foreach ( $subjects as $subject ) {
			$pageSubjects->createChildSubject( $subject );
		}

		$subjectContent = SubjectContent::newEmpty();
		$subjectContent->setPageSubjects( $pageSubjects );

		return $subjectContent;
	}

	/**
	 * @throws \MWException
	 */
	private function getMainContent( CreateSubjectsRequest $request ): Content {
		return new WikitextContent( str_replace(
			array_keys( $this->subjectIds ),
			array_values( $this->subjectIds ),
			$request->subjectsPageData->wikitext
		) );
	}

	/**
	 * @return array<int, Subject>
	 */
	private function buildSubjects( CreateSubjectsRequest $request ): array {
		$jsonSubjects = json_decode( $request->subjectsPageData->subjectsJson, true );
		if ( !is_array( $jsonSubjects ) ) {
			return [];
		}

		$subjects = [];
		foreach ( $jsonSubjects as $jsonSubject ) {
			if (
				empty( $jsonSubject[ 'id' ] )
				|| empty( $jsonSubject[ 'label' ] )
				|| empty( $jsonSubject[ 'schema' ] )
			) {
				continue;
			}

			/** @var array<string, mixed> $statements */
			$statements = !empty( $jsonSubject[ 'statements' ] ) && is_array( $jsonSubject[ 'statements' ] )
				? $jsonSubject[ 'statements' ] : [];

			$subject = Subject::createNew(
				guidGenerator: $this->guidGenerator,
				label: new SubjectLabel( (string)$jsonSubject[ 'label' ] ),
				schemaId: new SchemaName( (string)$jsonSubject[ 'schema' ] ),
				statements: $this->statementListPatcher->buildStatementList(
					statements: new StatementList(),
					patch: $statements
				)
			);

			$subjects[] = $subject;
			$this->subjectIds[ (string)$jsonSubject[ 'id' ] ] = $subject->getId()->text;
		}

		return $subjects;
	}

}
