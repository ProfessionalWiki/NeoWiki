<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class RestCreateSubjectPresenter implements CreateSubjectPresenter {

	private array $apiResponse = [];
	private int $statusCode = 201;
	private readonly SubjectPresentationSerializer $subjectSerializer;
	private readonly SchemaPresentationSerializer $schemaSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
		$this->schemaSerializer = new SchemaPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentCreated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->apiResponse = [
			'status' => 'created',
			'subjectId' => $subject->id,
			'violations' => ViolationSerializer::serializeMany( $violations ),
			'subject' => $this->subjectSerializer->serialize( $subject ),
		];

		if ( $schema !== null ) {
			$this->apiResponse['schema'] = $this->schemaSerializer->toArray( $schema );
		}

		$this->statusCode = 201;
	}

	public function presentSubjectAlreadyExists(): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'Subject already exists',
		];
		$this->statusCode = 409;
	}

	public function presentPageNotFound(): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'Page not found',
		];
		$this->statusCode = 404;
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
