<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectPresenter;

class RestSetMainSubjectPresenter implements SetMainSubjectPresenter {

	private array $apiResponse = [ 'status' => 'unchanged' ];
	private int $statusCode = 200;

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function presentMainSubjectChanged(): void {
		$this->apiResponse = [ 'status' => 'changed' ];
		$this->statusCode = 200;
	}

	public function presentNoChange(): void {
		$this->apiResponse = [ 'status' => 'unchanged' ];
		$this->statusCode = 200;
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => 'Subject not found on this page' ];
		$this->statusCode = 404;
	}

}
