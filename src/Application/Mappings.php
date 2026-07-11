<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * A set of {@see Mapping}s with the derivations the projection selection and duplicate-detection
 * need: the distinct targets, the Mappings for one target, and whether a (Schema, target) pair is
 * already claimed by another Mapping page. Pure, so it is unit-tested without a wiki.
 */
readonly class Mappings {

	/**
	 * @param Mapping[] $mappings
	 */
	public function __construct(
		private array $mappings,
	) {
	}

	/**
	 * @return Mapping[]
	 */
	public function forTarget( string $target ): array {
		return array_values(
			array_filter(
				$this->mappings,
				static fn ( Mapping $mapping ): bool => $mapping->target === $target
			)
		);
	}

	/**
	 * @return string[] Distinct targets, sorted, so the known-projection list is stable.
	 */
	public function targets(): array {
		$targets = array_values( array_unique(
			array_map( static fn ( Mapping $mapping ): string => $mapping->target, $this->mappings )
		) );

		sort( $targets );

		return $targets;
	}

	/**
	 * The Mapping, other than $excluding, that already claims the given (Schema, target) pair, or null
	 * when the pair is free. Used at save time to reject a duplicate; $excluding is the page being
	 * saved, so editing a Mapping in place does not collide with its own stored revision.
	 */
	public function conflictFor( SchemaName $schema, string $target, MappingName $excluding ): ?Mapping {
		foreach ( $this->mappings as $mapping ) {
			$isSelf = $mapping->name->getText() === $excluding->getText();

			if ( !$isSelf && $mapping->target === $target && $mapping->schema->getText() === $schema->getText() ) {
				return $mapping;
			}
		}

		return null;
	}

}
