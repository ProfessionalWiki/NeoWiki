<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaPresenter;

class RestGetSchemaPresenter implements GetSchemaPresenter {

	private string $apiResponse = '';

	public function getJson(): string {
		return $this->apiResponse;
	}

	public function presentSchema( string $json ): void {
		$this->apiResponse = (string)json_encode(
			[
				'schema' => json_decode( $json ),
			],
			JSON_PRETTY_PRINT
		);
	}

	public function presentSchemaNotFound(): void {
		$this->apiResponse = '{"schema":null}';
	}

}
