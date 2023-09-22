<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaPresenter;

class RestCreateSchemaPresenter implements CreateSchemaPresenter {

	private array $apiResponse = [];

	private function presentError( string $message ): void {
		$this->apiResponse = [
			'success' => false,
			'message' => $message
		];
	}

	public function getJson(): string {
		return (string)json_encode( $this->apiResponse, JSON_PRETTY_PRINT );
	}

	public function presentSchema( string $json ): void {
		$this->apiResponse = [
			'success' => true,
			'schema' => json_decode( $json )
		];
	}

	public function presentInvalidArguments(): void {
		$this->presentError( 'Invalid parameters for the schema creation.' );
	}

	public function presentPermissionsDenied(): void {
		$this->presentError( 'You don\'t have permissions to create a schema.' );
	}

	public function presentSchemaAlreadyExists(): void {
		$this->presentError( 'A schema by that name has already been registered.' );
	}

	public function presentSchemaCreationError( ?string $message ): void {
		$this->presentError( $message ?: 'It is not possible to create a schema at the moment.' );
	}

	public function presentInvalidTitle(): void {
		$this->presentError( 'Incorrect name for the schema.' );
	}

	public function presentNoChanges(): void {
		$this->presentError( 'There is no new data to update.' );
	}
}
