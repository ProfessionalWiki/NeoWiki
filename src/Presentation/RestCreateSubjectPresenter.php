<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class RestCreateSubjectPresenter implements CreateSubjectPresenter {

	private array $apiResponse = [];
	private int $statusCode = 201;

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentCreated( string $subjectId, array $violations ): void {
		$this->apiResponse = [
			'status' => 'created',
			'subjectId' => $subjectId,
			'violations' => ViolationSerializer::serializeMany( $violations ),
		];
		$this->statusCode = 201;
	}

	public function presentSubjectAlreadyExists(): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'Subject already exists',
		];
		$this->statusCode = 409;
	}

}
