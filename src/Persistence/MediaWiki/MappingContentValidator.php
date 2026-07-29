<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use RuntimeException;

/**
 * Validates the JSON of a Mapping page against the v1 format, in three stages:
 *
 *  1. Structural validation against mappingContentSchema.json (versioned, deliberately minimal).
 *  2. Semantic IRI-safety validation: every declared prefix namespace, and every class, predicate and
 *     datatype term across all Schema entries, must expand to a valid, safe absolute IRI (the #1029
 *     lesson). A term that cannot be resolved against the declared prefixes, or that would break out of
 *     its IRI, is rejected here rather than percent-encoded — a Mapping must reproduce the ontology's
 *     exact terms.
 *  3. Schema-name validation: each key under `schemas` must be a usable Schema name written exactly as
 *     its Schema page title. The projector matches keys against a Subject's Schema name byte for byte,
 *     while a page lookup normalizes, so "Person_X" would resolve to the Schema page yet never project.
 *     Requiring the canonical form keeps those two agreeing, and makes the read view's red or blue link
 *     an honest signal of whether the Schema is there.
 */
class MappingContentValidator {

	/**
	 * @var array<string, string> Pointer to message.
	 */
	private array $errors = [];

	public static function newInstance( TitleParser $titleParser ): self {
		$json = file_get_contents( __DIR__ . '/mappingContentSchema.json' );

		if ( !is_string( $json ) ) {
			throw new RuntimeException( 'Could not obtain JSON Schema' );
		}

		$schema = json_decode( $json );

		if ( !is_object( $schema ) ) {
			throw new RuntimeException( 'Failed to deserialize JSON Schema' );
		}

		return new self( $schema, $titleParser );
	}

	private function __construct(
		private object $jsonSchema,
		private TitleParser $titleParser
	) {
	}

	public function validate( string $config ): bool {
		$this->errors = [];

		$structuralErrors = $this->structuralErrors( $config );

		if ( $structuralErrors !== [] ) {
			$this->errors = $structuralErrors;
			return false;
		}

		// The structure is valid, so json_decode yields the expected associative array.
		/** @var array<string, mixed> $data */
		$data = json_decode( $config, true );
		$this->errors = $this->semanticErrors( $data );

		return $this->errors === [];
	}

	/**
	 * @return array<string, string>
	 */
	private function structuralErrors( string $config ): array {
		$validator = new Validator();
		$validator->setMaxErrors( 10 );

		$error = $validator->validate( json_decode( $config ), $this->jsonSchema )->error();

		return $error instanceof ValidationError ? ( new ErrorFormatter() )->format( $error, false ) : [];
	}

	/**
	 * The structure has already passed JSON-Schema validation, so the fields are present and well-typed;
	 * the guards here keep the analyser happy and make a hand-called validator robust to raw input.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function semanticErrors( array $data ): array {
		$prefixes = $this->stringMap( $data['prefixes'] ?? [] );
		$expander = new CurieExpander( $prefixes );

		$errors = $this->prefixErrors( $prefixes );

		$schemas = is_array( $data['schemas'] ?? null ) ? $data['schemas'] : [];
		foreach ( $schemas as $schemaName => $entry ) {
			$errors = array_merge( $errors, $this->schemaNameErrors( (string)$schemaName ) );

			if ( is_array( $entry ) ) {
				$errors = array_merge( $errors, $this->schemaErrors( (string)$schemaName, $entry, $expander ) );
			}
		}

		return $errors;
	}

	/**
	 * @return array<string, string>
	 */
	private function schemaNameErrors( string $schemaName ): array {
		$pointer = '/schemas/' . $schemaName;

		try {
			new SchemaName( $schemaName );
		}
		catch ( InvalidArgumentException $exception ) {
			return [ $pointer => 'The Schema name "' . $schemaName . '" cannot be used: ' . $exception->getMessage() . '.' ];
		}

		try {
			$canonical = $this->titleParser->parseTitle( $schemaName, NeoWikiExtension::NS_SCHEMA )->getText();
		}
		catch ( MalformedTitleException ) {
			return [ $pointer => 'The Schema name "' . $schemaName . '" is not a valid page name.' ];
		}

		if ( $canonical !== $schemaName ) {
			return [ $pointer => 'The Schema name "' . $schemaName . '" must be written as "' . $canonical . '", exactly as its Schema page is titled.' ];
		}

		return [];
	}

	/**
	 * @param array<string, string> $prefixes
	 * @return array<string, string>
	 */
	private function prefixErrors( array $prefixes ): array {
		$errors = [];

		foreach ( $prefixes as $label => $namespace ) {
			// The namespace also reaches the serializer's prefix table, so an unsafe one could inject a
			// `@prefix` declaration even when no term uses it. Reject it up front.
			if ( !CurieExpander::isSafeAbsoluteIri( $namespace ) ) {
				$errors['/prefixes/' . $label] = 'The prefix namespace "' . $namespace . '" is not a valid, safe absolute IRI.';
			}
		}

		return $errors;
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string, string>
	 */
	private function schemaErrors( string $schemaName, array $entry, CurieExpander $expander ): array {
		$base = '/schemas/' . $schemaName;
		$errors = [];

		$subject = $entry['subject'] ?? null;
		$class = is_array( $subject ) ? $this->stringOrNull( $subject['class'] ?? null ) : null;
		if ( $class !== null && $expander->expand( $class ) === null ) {
			$errors[$base . '/subject/class'] = $this->unresolvedTermMessage( $class );
		}

		$properties = is_array( $entry['properties'] ?? null ) ? $entry['properties'] : [];
		foreach ( $properties as $name => $propertyEntry ) {
			if ( is_array( $propertyEntry ) ) {
				$errors = array_merge( $errors, $this->propertyErrors( $base, (string)$name, $propertyEntry, $expander ) );
			}
		}

		return $errors;
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string, string>
	 */
	private function propertyErrors( string $base, string $name, array $entry, CurieExpander $expander ): array {
		$errors = [];

		$predicate = $this->stringOrNull( $entry['predicate'] ?? null );
		if ( $predicate !== null && $expander->expand( $predicate ) === null ) {
			$errors[$base . '/properties/' . $name . '/predicate'] = $this->unresolvedTermMessage( $predicate );
		}

		$datatype = $this->stringOrNull( $entry['datatype'] ?? null );
		if ( $datatype !== null && $expander->expand( $datatype ) === null ) {
			$errors[$base . '/properties/' . $name . '/datatype'] = $this->unresolvedTermMessage( $datatype );
		}

		return $errors;
	}

	private function stringOrNull( mixed $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	/**
	 * @return array<string, string>
	 */
	private function stringMap( mixed $value ): array {
		if ( !is_array( $value ) ) {
			return [];
		}

		$map = [];

		foreach ( $value as $key => $entry ) {
			if ( is_string( $key ) && is_string( $entry ) ) {
				$map[$key] = $entry;
			}
		}

		return $map;
	}

	private function unresolvedTermMessage( string $term ): string {
		return 'The term "' . $term . '" is not a declared CURIE or a valid, safe absolute IRI.';
	}

	/**
	 * @return array<string, string>
	 */
	public function getErrors(): array {
		return $this->errors;
	}

}
