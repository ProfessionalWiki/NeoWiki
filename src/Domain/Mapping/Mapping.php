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
 * On top of term substitution — a target class per Subject, one predicate per mapped property — the
 * format expresses both structural transformations of OntologyMapping.md: synthesizing the intermediate
 * nodes an event-centric target needs, and contributing a Subject's values to the Subjects it points at
 * for a target that wants them flat. The stored format is versioned and provisional; the
 * mapping-formalism question (OntologyMapping.md Q1, #995) stays open at the authoring level.
 */
readonly class Mapping {

	/**
	 * The one format version that can be read. The format so far grows by optional additions, which leave
	 * existing documents valid and unchanged in meaning, so this bumps only once a change breaks them. A
	 * page in any other version is unreadable and its projection is simply unknown.
	 */
	public const int FORMAT_VERSION = 1;

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
