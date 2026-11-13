<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement\UpdateStatementPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class RestUpdateStatementPresenter implements UpdateStatementPresenter {

	private array $apiResponse = [];
	private int $statusCode = 200;
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
	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations, int $revisionId ): void {
		$this->apiResponse = [
			'status' => 'updated',
			'subjectId' => $subject->id,
			'revisionId' => $revisionId,
			'violations' => ViolationSerializer::serializeMany( $violations ),
			'subject' => $this->subjectSerializer->serialize( $subject ),
		];

		if ( $schema !== null ) {
			$this->apiResponse['schema'] = $this->schemaSerializer->toArray( $schema );
		}

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

	public function presentConflict( GetSubjectResponseItem $currentSubject, int $currentRevisionId ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'The Statement changed after the base revision',
			'revisionId' => $currentRevisionId,
			'subject' => $this->subjectSerializer->serialize( $currentSubject ),
		];
		$this->statusCode = 409;
	}

	public function presentSaveFailed( ?string $errorMessage ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'The Subject could not be saved' . ( $errorMessage === null ? '' : ": $errorMessage" ),
		];
		$this->statusCode = 500;
	}

}
