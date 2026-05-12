<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use RuntimeException;

readonly class CreateSubjectAction {

	public function __construct(
		private CreateSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private IdGenerator $idGenerator,
		private SubjectAuthorizer $subjectAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaLookup $schemaLookup,
		private SelectStatementResolver $selectStatementResolver,
	) {
	}

	public function createSubject( CreateSubjectRequest $request ): void {
		if ( ( $request->isMainSubject && !$this->subjectAuthorizer->canCreateMainSubject(
				) ) || !$this->subjectAuthorizer->canCreateChildSubject() ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to create this subject' );
		}

		$subject = $this->buildSubject( $request );

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( new PageId( $request->pageId ) );

		try {
			if ( $request->isMainSubject ) {
				$pageSubjects->createMainSubject( $subject );
			} else {
				$pageSubjects->createChildSubject( $subject );
			}
		}
		catch ( RuntimeException ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$this->subjectRepository->savePageSubjects( $pageSubjects, new PageId( $request->pageId ), $request->comment );
		$this->presenter->presentCreated( $subject->id->text );
	}

	private function buildSubject( CreateSubjectRequest $request ): Subject {
		$schemaName = new SchemaName( $request->schemaName );

		return Subject::createNew(
			idGenerator: $this->idGenerator,
			label: new SubjectLabel( $request->label ),
			schemaName: $schemaName,
			statements: $this->statementListBuilder->build(
				$this->resolveSelectValues( $schemaName, $request->statements )
			)
		);
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return array<string, mixed>
	 */
	private function resolveSelectValues( SchemaName $schemaName, array $statements ): array {
		$schema = $this->schemaLookup->getSchema( $schemaName );

		if ( $schema === null ) {
			return $statements;
		}

		return $this->selectStatementResolver->resolve( $schema, $statements );
	}

}
