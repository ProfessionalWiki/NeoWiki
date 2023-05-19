<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

class RestGetSubjectPresenter implements GetSubjectPresenter {

	private array $apiResponse = [];

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->apiResponse = [
			'requestedId' => $response->requestedId,
			'subjects' => $this->buildSubjectsMap( $response->subjects ),
		];
	}

	/**
	 * @param GetSubjectResponseItem[] $subjects
	 */
	private function buildSubjectsMap( array $subjects ): array {
		$map = [];

		foreach ( $subjects as $subject ) {
			$map[$subject->id] = [
				'id' => $subject->id,
				'label' => $subject->label,
				'schema' => $subject->schemaId,
				'properties' => $subject->properties,
			];
		}

		return $map;
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [
			'subject' => null,
		];
	}

}
