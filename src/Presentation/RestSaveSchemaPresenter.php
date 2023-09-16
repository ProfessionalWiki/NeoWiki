<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Actions\SaveSchema\SaveSchemaPresenter;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Exception;

class RestSaveSchemaPresenter implements SaveSchemaPresenter {

	private array $apiResponse = [];

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getJson(): string {
		return (string)json_encode(
			$this->apiResponse,
			JSON_PRETTY_PRINT
		);
	}

	public function presentSchema( array $data ): void {
		$property = $this->buildPropertiesMap( $data );
		if ( !$property ) {
			return;
		}

		$this->apiResponse = [
			'success' => true,
			'data' => [
				'description' => $data[ 'description' ] ?? '',
				'propertyDefinitions' => $property
			]
		];
	}

	private function buildPropertiesMap( array $data ): ?array {
		$map = [];
		$properties = (array)( $data[ 'propertyDefinitions' ] ?? [] );
		/** @var iterable<string, array> $properties */

		try {
			foreach ( $properties as $key => $value ) {

				$property = PropertyDefinition::fromJson(
					$value,
					NeoWikiExtension::getInstance()->getValueFormatLookup()
				);

				$map[ $key ] = $property->toJson();
			}

			return $map;

		} catch ( Exception $e ) {
			$this->presentError( $e->getMessage() );
		}

		return null;
	}

	public function presentError( string $message ): void {
		$this->apiResponse = [
			'success' => false,
			'message' => $message
		];
	}

}
