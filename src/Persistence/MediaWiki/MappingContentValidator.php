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
 * Validates the JSON of a Mapping page against the Mapping format, in three stages:
 *
 *  1. Structural validation against mappingContentSchema.json (versioned, deliberately minimal).
 *  2. Semantic IRI-safety validation: every declared prefix namespace, and every class, label predicate,
 *     node class, node link predicate, predicate and datatype term across all Schema entries, must expand
 *     to a valid, safe absolute IRI (the #1029 lesson). A term that cannot be resolved against the declared
 *     prefixes, or that would break out of its IRI, is rejected here rather than percent-encoded — a
 *     Mapping must reproduce the ontology's exact terms. The node graph is checked in the same stage:
 *     every node reference names a declared node, and the shapes the projector supports are enforced
 *     (see {@see nodeGraphErrors()}).
 *  3. Schema-name validation: each key under `schemas` must be a usable Schema name written exactly as
 *     its Schema page title. The projector matches keys against a Subject's Schema name byte for byte,
 *     while a page lookup normalizes, so "Person_X" would resolve to the Schema page yet never project.
 *     Requiring the canonical form keeps those two agreeing, and makes the read view's red or blue link
 *     an honest signal of whether the Schema is there.
 *
 * Property, node-attached property, and contribution relation names are **not** checked against the
 * actual Schema, for the same reason Schema names are not checked for existence: a Mapping may be
 * authored or installed before the Schema it maps.
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
	 * Runs only after the structural stage passed, so every field that is present has the type the JSON
	 * Schema declares; only optional fields need an absent case.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function semanticErrors( array $data ): array {
		/** @var array<string, string> $prefixes */
		$prefixes = $data['prefixes'] ?? [];
		$expander = new CurieExpander( $prefixes );

		$errors = $this->prefixErrors( $prefixes );

		/** @var array<string, array<string, mixed>> $schemas */
		$schemas = $data['schemas'];

		foreach ( $schemas as $schemaName => $entry ) {
			$errors = array_merge(
				$errors,
				$this->schemaNameErrors( (string)$schemaName ),
				$this->schemaErrors( (string)$schemaName, $entry, $expander )
			);
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

		return array_merge(
			$this->subjectErrors( $base, $this->arrayValue( $entry, 'subject' ), $expander ),
			$this->nodeTermErrors( $base, $this->arrayValue( $entry, 'nodes' ), $expander ),
			$this->nodeGraphErrors( $base, $entry ),
			$this->propertiesErrors( $base . '/properties', $this->arrayValue( $entry, 'properties' ), $expander ),
			$this->contributionsErrors( $base, $entry, $expander ),
		);
	}

	/**
	 * @param array<mixed> $subject
	 * @return array<string, string>
	 */
	private function subjectErrors( string $base, array $subject, CurieExpander $expander ): array {
		return array_merge(
			$this->termErrors( $base . '/subject/class', $subject['class'] ?? null, $expander ),
			$this->termErrors( $base . '/subject/labelPredicate', $subject['labelPredicate'] ?? null, $expander ),
		);
	}

	/**
	 * @param array<mixed> $nodes
	 * @return array<string, string>
	 */
	private function nodeTermErrors( string $base, array $nodes, CurieExpander $expander ): array {
		$errors = [];

		foreach ( $nodes as $key => $node ) {
			if ( is_array( $node ) ) {
				$errors = array_merge(
					$errors,
					$this->termErrors( $base . '/nodes/' . $key . '/class', $node['class'] ?? null, $expander ),
					$this->termErrors(
						$base . '/nodes/' . $key . '/linkPredicate',
						$node['linkPredicate'] ?? null,
						$expander
					),
				);
			}
		}

		return $errors;
	}

	/**
	 * The shape rules the projector's node handling relies on: every `node` and `parent` names a declared
	 * node, the parent chain reaches the Subject rather than looping, and a per-value node stays a leaf
	 * carrying one property — the projector mints one instance of it per value of that property, so a
	 * second referrer or a child hanging off it would have no single instance to attach to.
	 *
	 * @param array<mixed> $entry
	 * @return array<string, string>
	 */
	private function nodeGraphErrors( string $base, array $entry ): array {
		$nodes = $this->arrayValue( $entry, 'nodes' );
		$errors = $this->numericNodeKeyErrors( $base, $nodes );

		foreach ( $nodes as $key => $node ) {
			$parent = is_array( $node ) ? $this->stringOrNull( $node['parent'] ?? null ) : null;

			if ( $parent === null ) {
				continue;
			}

			$pointer = $base . '/nodes/' . $key;

			if ( !array_key_exists( $parent, $nodes ) ) {
				$errors[$pointer . '/parent'] = 'The node "' . $parent . '" is not declared.';
			}
			elseif ( $this->isPerValue( $nodes[$parent] ) ) {
				$errors[$pointer . '/parent'] = 'The node "' . $parent . '" is per value, so it cannot be a parent.';
			}
			elseif ( self::chainLoops( (string)$key, $nodes ) ) {
				$errors[$pointer . '/parent'] = 'The node "' . $key . '" is its own ancestor.';
			}
		}

		return array_merge( $errors, $this->perValueReferrerErrors( $base, $nodes, $this->arrayValue( $entry, 'properties' ) ) );
	}

	/**
	 * An all-digit node key survives JSON parsing as an integer key, which the JSON Schema's
	 * `propertyNames` pattern cannot see because a pattern only constrains strings. Rejecting it here
	 * keeps every stored node key identifier-shaped — and such a node is unreachable anyway, since the
	 * `parent` and `node` references that would name it are pattern-checked.
	 *
	 * @param array<mixed> $nodes
	 * @return array<string, string>
	 */
	private function numericNodeKeyErrors( string $base, array $nodes ): array {
		$errors = [];

		foreach ( array_keys( $nodes ) as $key ) {
			if ( !is_string( $key ) ) {
				$errors[$base . '/nodes/' . $key] =
					'The node key "' . $key . '" must start with a letter or an underscore.';
			}
		}

		return $errors;
	}

	/**
	 * @param array<mixed> $nodes
	 * @param array<mixed> $properties
	 * @return array<string, string>
	 */
	private function perValueReferrerErrors( string $base, array $nodes, array $properties ): array {
		$errors = [];
		$referrers = [];

		foreach ( $properties as $name => $property ) {
			$node = is_array( $property ) ? $this->stringOrNull( $property['node'] ?? null ) : null;

			if ( $node === null ) {
				continue;
			}

			if ( !array_key_exists( $node, $nodes ) ) {
				$errors[$base . '/properties/' . $name . '/node'] = 'The node "' . $node . '" is not declared.';
				continue;
			}

			$referrers[$node] ??= 0;
			$referrers[$node]++;

			if ( $referrers[$node] > 1 && $this->isPerValue( $nodes[$node] ) ) {
				$errors[$base . '/properties/' . $name . '/node'] =
					'The node "' . $node . '" is per value, so only one property can attach to it.';
			}
		}

		return $errors;
	}

	private function isPerValue( mixed $node ): bool {
		return is_array( $node ) && ( $node['per'] ?? null ) === 'value';
	}

	/**
	 * @param array<mixed> $nodes
	 */
	private static function chainLoops( string $key, array $nodes ): bool {
		$seen = [];
		$current = $key;

		while ( is_string( $current ) && is_array( $nodes[$current] ?? null ) ) {
			if ( array_key_exists( $current, $seen ) ) {
				return true;
			}

			$seen[$current] = true;
			$current = $nodes[$current]['parent'] ?? null;
		}

		return false;
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string, string>
	 */
	private function contributionsErrors( string $base, array $entry, CurieExpander $expander ): array {
		$errors = [];

		foreach ( $this->arrayValue( $entry, 'contributions' ) as $relation => $properties ) {
			if ( is_array( $properties ) ) {
				$errors = array_merge(
					$errors,
					$this->propertiesErrors( $base . '/contributions/' . $relation, $properties, $expander )
				);
			}
		}

		return $errors;
	}

	/**
	 * @param array<mixed> $properties
	 * @return array<string, string>
	 */
	private function propertiesErrors( string $base, array $properties, CurieExpander $expander ): array {
		$errors = [];

		foreach ( $properties as $name => $property ) {
			if ( is_array( $property ) ) {
				$errors = array_merge(
					$errors,
					$this->termErrors( $base . '/' . $name . '/predicate', $property['predicate'] ?? null, $expander ),
					$this->termErrors( $base . '/' . $name . '/datatype', $property['datatype'] ?? null, $expander ),
				);
			}
		}

		return $errors;
	}

	/**
	 * @return array<string, string>
	 */
	private function termErrors( string $pointer, mixed $term, CurieExpander $expander ): array {
		$term = $this->stringOrNull( $term );

		if ( $term === null || $expander->expand( $term ) !== null ) {
			return [];
		}

		return [ $pointer => $this->unresolvedTermMessage( $term ) ];
	}

	/**
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	private function arrayValue( array $data, string $key ): array {
		return is_array( $data[$key] ?? null ) ? $data[$key] : [];
	}

	private function stringOrNull( mixed $value ): ?string {
		return is_string( $value ) ? $value : null;
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
