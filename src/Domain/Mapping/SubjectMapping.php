<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * How a {@see SchemaMapping} projects the Subject itself: its target class and, optionally, a second
 * predicate for its label. `rdfs:label` is emitted regardless, so `$labelPredicate` adds a target-ontology
 * label term (`foaf:name`, `crm:P1_is_identified_by`, …) rather than replacing it.
 *
 * Absent from a Schema entry that only {@see SchemaMapping::$contributions contributes} to other Subjects.
 */
readonly class SubjectMapping {

	public function __construct(
		public string $class,
		public ?string $labelPredicate = null,
	) {
	}

}
