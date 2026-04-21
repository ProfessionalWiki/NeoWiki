<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

readonly class GetPageSubjectsQuery {

	public function __construct(
		private GetPageSubjectsPresenter $presenter,
		private SubjectRepository $subjectRepository,
	) {
	}

	public function execute( int $pageId ): void {
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( new PageId( $pageId ) );

		$mainSubject = $pageSubjects->getMainSubject();
		$subjectItems = [];

		if ( $mainSubject !== null ) {
			$subjectItems[$mainSubject->id->text] = $this->buildResponseItem( $mainSubject );
		}

		foreach ( $pageSubjects->getChildSubjects()->asArray() as $childSubject ) {
			$subjectItems[$childSubject->id->text] = $this->buildResponseItem( $childSubject );
		}

		$this->presenter->presentPageSubjects(
			new GetPageSubjectsResponse(
				pageId: $pageId,
				mainSubjectId: $mainSubject?->id->text,
				subjects: $subjectItems,
			)
		);
	}

	private function buildResponseItem( Subject $subject ): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaName: $subject->getSchemaName()->getText(),
			statements: $this->arrayifyStatements( $subject->getStatements() ),
			pageId: null,
			pageTitle: null,
		);
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
