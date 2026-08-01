<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * Maps one NeoWiki property (referenced by name from the Mapping's Schema) to a target-ontology
 * predicate. The predicate and optional datatype are raw CURIEs or absolute IRIs; they are expanded
 * against the Mapping's prefixes by a {@see CurieExpander} at validation and projection time.
 */
readonly class PropertyMapping {

	/**
	 * @param string|null $node The key of a {@see NodeMapping} on the same Schema entry. The property's
	 *   values then attach to that synthesized node's instance instead of to the Subject.
	 */
	public function __construct(
		public string $predicate,
		public ?string $language = null,
		public ?string $datatype = null,
		public ?string $node = null,
	) {
	}

}
