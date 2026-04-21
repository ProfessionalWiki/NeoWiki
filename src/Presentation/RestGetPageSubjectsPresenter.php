<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

class RestGetPageSubjectsPresenter implements GetPageSubjectsPresenter {

	private array $apiResponse = [];

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentPageSubjects( GetPageSubjectsResponse $response ): void {
		$this->apiResponse = [
			'pageId' => $response->pageId,
			'mainSubjectId' => $response->mainSubjectId,
			'subjects' => $this->buildSubjectsMap( $response->subjects ),
		];
	}

	/**
	 * @param array<string, GetSubjectResponseItem> $subjects
	 * @return array<string, array<string, mixed>>
	 */
	private function buildSubjectsMap( array $subjects ): array {
		$map = [];

		foreach ( $subjects as $subject ) {
			$map[$subject->id] = [
				'id' => $subject->id,
				'label' => $subject->label,
				'schema' => $subject->schemaName,
				'statements' => $subject->statements,
			];
		}

		return $map;
	}

}
