<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectPresenter;

class RestMoveSubjectPresenter implements MoveSubjectPresenter {

	private array $apiResponse = [ 'status' => 'unchanged' ];
	private int $statusCode = 200;

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function presentMoved(): void {
		$this->apiResponse = [ 'status' => 'changed' ];
		$this->statusCode = 200;
	}

	public function presentNoChange(): void {
		$this->apiResponse = [ 'status' => 'unchanged' ];
		$this->statusCode = 200;
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => 'Subject not found' ];
		$this->statusCode = 404;
	}

	public function presentTargetPageNotFound(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => 'Target page not found' ];
		$this->statusCode = 404;
	}

	public function presentSourcePageNotFound(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => 'Page not found' ];
		$this->statusCode = 404;
	}

	public function presentSubjectAlreadyOnTargetPage(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => 'Subject is already on the target page' ];
		$this->statusCode = 409;
	}

	public function presentMoveIncomplete(): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'The subject was removed from its original page but could not be added to the target page, and could not be put back',
		];
		$this->statusCode = 500;
	}

}
