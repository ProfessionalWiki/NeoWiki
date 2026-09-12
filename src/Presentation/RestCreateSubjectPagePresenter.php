<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage\CreateSubjectPagePresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

class RestCreateSubjectPagePresenter implements CreateSubjectPagePresenter {

	/** @var array<string, mixed> */
	private array $apiResponse = [];
	private int $statusCode = 201;
	private readonly SubjectPresentationSerializer $subjectSerializer;
	private readonly SchemaPresentationSerializer $schemaSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
		$this->schemaSerializer = new SchemaPresentationSerializer();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentCreated(
		GetSubjectResponseItem $subject,
		PageIdentifiers $page,
		?Schema $schema,
		array $violations
	): void {
		$this->apiResponse = [
			'status' => 'created',
			'subjectId' => $subject->id,
			'pageId' => $page->getId()->id,
			'pageTitle' => $page->getTitle(),
			'violations' => ViolationSerializer::serializeMany( $violations ),
			'subject' => $this->subjectSerializer->serialize( $subject ),
		];

		if ( $schema !== null ) {
			$this->apiResponse['schema'] = $this->schemaSerializer->toArray( $schema );
		}

		$this->statusCode = 201;
	}

	public function presentPageTitleTaken( string $pageTitle ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => "A page named \"$pageTitle\" already exists",
			'pageTitle' => $pageTitle,
		];
		$this->statusCode = 409;
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

	public function presentPageNotCreated( string $pageTitle, ?string $errorMessage ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => "The page \"$pageTitle\" could not be created"
				. ( $errorMessage === null ? '' : ": $errorMessage" ),
		];
		$this->statusCode = 500;
	}

}
