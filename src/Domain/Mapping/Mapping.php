<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * An ontology mapping: the correspondence between the native NeoWiki Schemas and one target ontology
 * (OntologyMapping.md). A Mapping is a first-class wiki object, one page per target ontology, the page
 * title being the target/projection name ([ADR 17](../../../docs/adr/017-names-as-identifiers.md)). The
 * page holds an entry for every mapped Schema, so a Schema is mapped by adding an entry rather than by
 * creating a page — and a page cannot list the same Schema twice, so uniqueness needs no save-time check.
 *
 * This is the deliberately minimal v1 shape: term substitution only (a target class for each Subject and
 * one predicate per mapped property), no intermediate-node synthesis. The stored format is versioned and
 * provisional; the mapping-formalism question (OntologyMapping.md Q1, #995) stays open.
 */
readonly class Mapping {

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI, shared by every entry, for
	 *   expanding the CURIEs used in the subject classes and the property predicates/datatypes.
	 * @param array<string, SchemaMapping> $schemas The per-Schema entries, keyed by Schema name.
	 */
	public function __construct(
		public MappingName $name,
		public array $prefixes,
		public array $schemas,
	) {
	}

	public function forSchema( SchemaName $schema ): ?SchemaMapping {
		return $this->schemas[$schema->getText()] ?? null;
	}

}
