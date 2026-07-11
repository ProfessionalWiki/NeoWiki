<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * An ontology mapping: the correspondence between one native NeoWiki Schema and one target ontology
 * (OntologyMapping.md). A Mapping is a first-class wiki object, separate from the Schema it references
 * ([ADR 17](../../../docs/adr/017-names-as-identifiers.md)), so the Schema stays clean and can carry
 * several mappings — one per target — installed independently.
 *
 * This is the deliberately minimal v1 shape: term substitution only (a target class for the Subject
 * and one predicate per mapped property), no intermediate-node synthesis. The stored format is
 * versioned and provisional; the mapping-formalism question (OntologyMapping.md Q1, #995) stays open.
 */
readonly class Mapping {

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI, for expanding the CURIEs
	 *   used in $subjectClass and the property predicates/datatypes.
	 */
	public function __construct(
		public MappingName $name,
		public SchemaName $schema,
		public string $target,
		public array $prefixes,
		public string $subjectClass,
		public PropertyMappings $properties,
	) {
	}

}
