<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class RestReplaceSubjectPresenter implements ReplaceSubjectPresenter {

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
	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->apiResponse = [
			'status' => 'updated',
			'subjectId' => $subject->id,
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

}
