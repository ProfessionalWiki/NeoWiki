<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectPresenter;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class RestReplaceSubjectPresenter implements ReplaceSubjectPresenter {

	private array $apiResponse = [];
	private int $statusCode = 200;

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentUpdated( string $subjectId, array $violations ): void {
		$this->apiResponse = [
			'status' => 'updated',
			'subjectId' => $subjectId,
			'violations' => ViolationSerializer::serializeMany( $violations ),
		];
		$this->statusCode = 200;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'Validation failed',
			'violations' => ViolationSerializer::serializeMany( $violations ),
		];
		$this->statusCode = 422;
	}

}
