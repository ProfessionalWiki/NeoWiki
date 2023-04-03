<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;

class RestGetSubjectPresenter implements GetSubjectPresenter {

	private array $apiResponse = [];

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->apiResponse = [
			'subject' => [
				'id' => $response->id,
				'label' => $response->label,
				'types' => $response->types,
				'properties' => $response->properties,
			]
		];
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [
			'subject' => null,
		];
	}

}
