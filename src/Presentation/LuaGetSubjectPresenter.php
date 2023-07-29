<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;

class LuaGetSubjectPresenter implements GetSubjectPresenter {

	private ?GetSubjectResponse $response = null;

	public function __construct() {
	}

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->response = $response;
	}

	public function presentSubjectNotFound(): void {
	}

	public function getLuaResponse(): ?array {
		if ( $this->response === null || !array_key_exists( $this->response->requestedId, $this->response->subjects ) ) {
			return null;
		}

		$subject = $this->response->subjects[$this->response->requestedId];

		return [
			'id' => $subject->id,
			'label' => $subject->label,
			'schema' => $subject->schemaId,
			'statements' => array_map( $this->toOneBasedArray( ... ), $subject->statements ),
			'pageId' => $subject->pageId,
			'pageTitle' => $subject->pageTitle,
		];
	}

	/**
	 * @param array<mixed, mixed> $array
	 * @return array<int, mixed>
	 */
	private function toOneBasedArray( array $array ): array {
		$oneBasedArray = [];

		foreach ( array_values( $array ) as $key => $value ) {
			$oneBasedArray[$key + 1] = $value;
		}

		return $oneBasedArray;
	}

}
