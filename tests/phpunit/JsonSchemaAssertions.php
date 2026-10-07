<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use Opis\JsonSchema\Validator;

trait JsonSchemaAssertions {

	/**
	 * Whether a standard validator accepts the value under the schema. Both go through JSON, which
	 * turns PHP arrays into the objects and lists the validator expects.
	 *
	 * @param array<string, mixed>|bool $schema
	 */
	private function jsonSchemaAccepts( array|bool $schema, mixed $value ): bool {
		return ( new Validator() )
			->validate( json_decode( (string)json_encode( $value ) ), json_decode( (string)json_encode( $schema ) ) )
			->isValid();
	}

}
