<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;

class SchemaContentValidator {

	/**
	 * @var string[]
	 */
	private array $errors = [];

	public static function newInstance(): self {
		$json = file_get_contents( __DIR__ . '/schemaContentSchema.json' );

		if ( !is_string( $json ) ) {
			throw new RuntimeException( 'Could not obtain JSON Schema' );
		}

		$schema = json_decode( $json );

		if ( !is_object( $schema ) ) {
			throw new RuntimeException( 'Failed to deserialize JSON Schema' );
		}

		return new self( $schema );
	}

	private function __construct(
		private object $jsonSchema
	) {
	}

	public function validate( string $config ): bool {
		$validator = new Validator();
		$validator->setMaxErrors( 10 );

		$validationResult = $validator->validate( json_decode( $config ), $this->jsonSchema );

		$error = $validationResult->error();

		if ( $error !== null ) {
			$this->errors = $this->formatErrors( $error );
		}

		return $error === null;
	}

	/**
	 * @return string[]
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * @return array<string, string>
	 */
	private function formatErrors( ValidationError $error ): array {
		return ( new ErrorFormatter() )->format( $error, false );
	}

}
