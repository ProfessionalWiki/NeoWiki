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
		if ( $this->response === null ) {
			return null;
		}

		return [
			'id' => $this->response->id,
			'label' => $this->response->label,
			'schema' => $this->response->schemaId,
			'properties' => array_map( $this->toOneBasedArray( ... ), $this->response->properties ),
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
