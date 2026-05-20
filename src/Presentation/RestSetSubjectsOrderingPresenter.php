<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingPresenter;

class RestSetSubjectsOrderingPresenter implements SetSubjectsOrderingPresenter {

	private array $apiResponse = [ 'status' => 'unchanged' ];
	private int $statusCode = 200;

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function presentOrderingChanged(): void {
		$this->apiResponse = [ 'status' => 'changed' ];
		$this->statusCode = 200;
	}

	public function presentNoChange(): void {
		$this->apiResponse = [ 'status' => 'unchanged' ];
		$this->statusCode = 200;
	}

	public function presentInvalidOrdering( string $reason ): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => $reason ];
		$this->statusCode = 400;
	}

}
