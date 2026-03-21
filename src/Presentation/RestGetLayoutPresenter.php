<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetLayout\GetLayoutPresenter;

class RestGetLayoutPresenter implements GetLayoutPresenter {

	private string $apiResponse = '';

	public function getJson(): string {
		return $this->apiResponse;
	}

	public function presentLayout( string $json ): void {
		$this->apiResponse = (string)json_encode(
			[
				'layout' => json_decode( $json ),
			],
			JSON_PRETTY_PRINT
		);
	}

	public function presentLayoutNotFound(): void {
		$this->apiResponse = '{"layout":null}';
	}

}
