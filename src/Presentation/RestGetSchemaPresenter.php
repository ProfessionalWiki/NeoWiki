<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaPresenter;

class RestGetSchemaPresenter implements GetSchemaPresenter {

	private string $apiResponse = '';

	public function getJson(): string {
		return $this->apiResponse;
	}

	public function presentSchema( string $schemaJson ): void {
		$this->apiResponse = '{"schema":' . $schemaJson . '}';
	}

	public function presentSchemaNotFound(): void {
		$this->apiResponse = '{"schema":null}';
	}

}
