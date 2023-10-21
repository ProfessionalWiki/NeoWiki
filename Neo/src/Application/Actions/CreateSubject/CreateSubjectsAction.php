<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\RevisionUpdater;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use RuntimeException;

class CreateSubjectsAction {

	public function __construct(
		private readonly SubjectRepository $subjectRepository,
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
		] );
	}

	/**
	 * @param array<int, Subject> $subjects
	 */
	private function getSubjectsContent( array $subjects, CreateSubjectsRequest $request ): SubjectContent {
		// TODO: we should not need to talk to the DB to find out what subjects already exist.
		// We are starting with a revision object. Our task is to add the new Subjects without removing the existing ones.
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $request->pageId );

		foreach ( $subjects as $subject ) {
			$pageSubjects->createChildSubject( $subject );
		}

		$subjectContent = SubjectContent::newEmpty();
		$subjectContent->setPageSubjects( $pageSubjects );

		return $subjectContent;
	}

	/**
	 * TODO: this should probably use SubjectContentDataDeserializer, or otherwise a similar service.
	 * @return array<int, Subject>
	 */
	private function buildSubjects( CreateSubjectsRequest $request ): array {
		$jsonSubjects = json_decode( $request->subjectsJson, true );
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

			$subjects[] = new Subject(
				id: new SubjectId( (string)$jsonSubject['id'] ),
				label: new SubjectLabel( (string)$jsonSubject[ 'label' ] ),
				schemaId: new SchemaName( (string)$jsonSubject[ 'schema' ] ),
				// FIXME: We should not use statementListPatcher to build a statement list as a hack
				statements: $this->statementListPatcher->buildStatementList(
					statements: new StatementList(),
					patch: $statements
				)
			);
		}

		return $subjects;
	}

}
