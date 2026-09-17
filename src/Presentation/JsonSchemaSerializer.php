<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use RuntimeException;
use stdClass;

/**
 * Expresses a Schema as a JSON Schema (draft 2020-12) describing a Subject of that Schema, in the
 * JSON the Subject endpoints accept and return. JsonSchemaMatchesValidatorTest keeps the value
 * subschemas in step with what the Property Types validate.
 */
readonly class JsonSchemaSerializer {

	private const string DIALECT = 'https://json-schema.org/draft/2020-12/schema';

	/**
	 * @param string $documentUrl The absolute URL this document is served from, used as its `$id`.
	 */
	public function __construct(
		private string $documentUrl,
	) {
	}

	public function serialize( Schema $schema ): string {
		$json = json_encode(
			$this->subjectSchema( $schema ),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( $json === false ) {
			throw new RuntimeException( 'Failed to JSON encode the JSON Schema document' );
		}

		return $json;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function subjectSchema( Schema $schema ): array {
		return [
			'$schema' => self::DIALECT,
			'$id' => $this->documentUrl,
			'title' => $schema->getName()->getText(),
		] + $this->description( $schema->getDescription() ) + [
			'type' => 'object',
			'required' => [ 'statements' ],
			'properties' => [
				'schema' => [ 'const' => $schema->getName()->getText() ],
				'label' => [ 'type' => [ 'string', 'null' ] ],
				'statements' => $this->statementsSchema( $schema->getAllProperties() ),
			],
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function description( string $description ): array {
		return $description === '' ? [] : [ 'description' => $description ];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function statementsSchema( PropertyDefinitions $properties ): array {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'required' => $this->requiredPropertyNames( $properties ),
			'properties' => $this->statementSchemaPerProperty( $properties ),
		];
	}

	/**
	 * @return string[]
	 */
	private function requiredPropertyNames( PropertyDefinitions $properties ): array {
		return array_keys(
			$properties->filter( static fn ( PropertyDefinition $definition ): bool => $definition->isRequired() )->asMap()
		);
	}

	/**
	 * @return array<string, mixed>|stdClass
	 */
	private function statementSchemaPerProperty( PropertyDefinitions $properties ): array|stdClass {
		$schemas = array_map( $this->statementSchema( ... ), $properties->asMap() );

		// An empty PHP array encodes as `[]`, not the object a JSON Schema map has to be.
		return $schemas === [] ? new stdClass() : $schemas;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function statementSchema( PropertyDefinition $definition ): array {
		return [ 'type' => 'object' ] + $this->description( $definition->getDescription() ) + [
			'required' => [ 'propertyType', 'value' ],
			'properties' => [
				'propertyType' => [ 'const' => $definition->getPropertyType() ],
				'value' => $definition->toJsonSchema(),
			],
		];
	}

}
