<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetView\GetViewPresenter;

class RestGetViewPresenter implements GetViewPresenter {

	private string $apiResponse = '';

	public function getJson(): string {
		return $this->apiResponse;
	}

	public function presentView( string $json ): void {
		$this->apiResponse = (string)json_encode(
			[
				'view' => json_decode( $json ),
			],
			JSON_PRETTY_PRINT
		);
	}

	public function presentViewNotFound(): void {
		$this->apiResponse = '{"view":null}';
	}

}
