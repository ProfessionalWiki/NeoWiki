<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * One Schema's entry within a page-level {@see Mapping}: the target class given to each Subject of the
 * Schema and the per-property predicates. The CURIEs used here are expanded against the Mapping's
 * page-level prefixes by a {@see CurieExpander} at validation and projection time.
 */
readonly class SchemaMapping {

	public function __construct(
		public SchemaName $schema,
		public string $subjectClass,
		public PropertyMappings $properties,
	) {
	}

}
