<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

class RestGetPageSubjectsPresenter implements GetPageSubjectsPresenter {

	private array $apiResponse = [];
	private readonly SubjectPresentationSerializer $subjectSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentPageSubjects( GetPageSubjectsResponse $response ): void {
		$body = [
			'pageId' => $response->pageId,
			'mainSubjectId' => $response->mainSubjectId,
			'subjects' => $this->buildSubjectsMap( $response->subjects ),
		];

		if ( $response->referencedSubjects !== null ) {
			$body['referencedSubjects'] = $this->buildSubjectsMap( $response->referencedSubjects );
		}

		if ( $response->schemas !== null ) {
			$body['schemas'] = $this->buildSchemasMap( $response->schemas );
		}

		$this->apiResponse = $body;
	}

	/**
	 * @param array<string, GetSubjectResponseItem> $subjects
	 * @return array<string, array<string, mixed>>
	 */
	private function buildSubjectsMap( array $subjects ): array {
		$map = [];

		foreach ( $subjects as $subject ) {
			$map[$subject->id] = $this->subjectSerializer->serialize( $subject );
		}

		return $map;
	}

	/**
	 * @param array<string, string> $schemas Schema name → JSON-encoded schema
	 * @return array<string, mixed> Schema name → decoded schema body
	 */
	private function buildSchemasMap( array $schemas ): array {
		$map = [];

		foreach ( $schemas as $name => $json ) {
			$map[$name] = json_decode( $json, true );
		}

		return $map;
	}

}
