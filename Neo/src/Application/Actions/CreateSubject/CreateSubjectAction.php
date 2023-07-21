<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use RuntimeException;

class CreateSubjectAction {

	public function __construct(
		private readonly CreateSubjectPresenter $presenter,
		private readonly SubjectRepository $subjectRepository,
		private readonly GuidGenerator $guidGenerator,
		private readonly SubjectActionAuthorizer $subjectActionAuthorizer
	) {
	}

	public function createSubject( CreateSubjectRequest $request ): void {
		if ( ( $request->isMainSubject && !$this->subjectActionAuthorizer->canCreateMainSubject(
				) ) || !$this->subjectActionAuthorizer->canCreateChildSubject() ) {
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
		catch ( RuntimeException $e ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		$this->subjectRepository->savePageSubjects( $pageSubjects, new PageId( $request->pageId ) );
		$this->presenter->presentCreated( $subject->id->text );
	}

	private function buildSubject( CreateSubjectRequest $request ): Subject {
		return Subject::createNew(
			guidGenerator: $this->guidGenerator,
			label: new SubjectLabel( $request->label ),
			schemaId: new SchemaId( $request->schemaId ),
			properties: $this->buildSubjectProperties( $request ),
		);
	}

	private function buildSubjectProperties( CreateSubjectRequest $request ): StatementList {
		return new StatementList(
			array_map(
				function ( $value ) {
					if ( $this->isRelationValue( $value ) ) {
						return $this->assignRelationIds( $value );
					}
					return $value;
				},
				$request->properties
			)
		);
	}

	public function isRelationValue( mixed $value ): bool {
		return is_array( $value ) && isset( $value[0]['target'] );
	}

	private function assignRelationIds( array $value ): array {
		return array_map(
			function ( $item ) {
				if ( is_array( $item ) && isset( $item['target'] ) ) {
					$item['id'] = RelationId::createNew( $this->guidGenerator )->asString();
				}
				return $item;
			},
			$value
		);
	}

}
