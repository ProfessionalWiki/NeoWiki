<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * The per-property rules of a {@see Mapping}, keyed by NeoWiki property name.
 */
readonly class PropertyMappings {

	/**
	 * @param array<string, PropertyMapping> $mappings Keyed by NeoWiki property name.
	 */
	public function __construct(
		private array $mappings = [],
	) {
	}

	public function get( string $propertyName ): ?PropertyMapping {
		return $this->mappings[$propertyName] ?? null;
	}

	/**
	 * @return array<string, PropertyMapping> Keyed by NeoWiki property name.
	 */
	public function asArray(): array {
		return $this->mappings;
	}

}
