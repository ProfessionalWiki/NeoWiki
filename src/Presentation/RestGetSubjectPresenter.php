<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

class RestGetSubjectPresenter implements GetSubjectPresenter {

	private array $apiResponse = [];
	private ?int $revisionId = null;
	private readonly SubjectPresentationSerializer $subjectSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	/**
	 * The revision of its page the requested Subject is read from, served beside it for a write to be based on.
	 */
	public function setRevisionId( int $revisionId ): void {
		$this->revisionId = $revisionId;
	}

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->apiResponse = [
			'requestedId' => $response->requestedId,
			'subjects' => $this->buildSubjectsMap( $response->subjects ),
		];

		if ( $this->revisionId !== null ) {
			$this->apiResponse['revisionId'] = $this->revisionId;
		}
	}

	/**
	 * @param GetSubjectResponseItem[] $subjects
	 */
	private function buildSubjectsMap( array $subjects ): array {
		$map = [];

		foreach ( $subjects as $subject ) {
			$map[$subject->id] = $this->subjectSerializer->serialize( $subject );
		}

		return $map;
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [
			'subject' => null,
		];
	}

}
