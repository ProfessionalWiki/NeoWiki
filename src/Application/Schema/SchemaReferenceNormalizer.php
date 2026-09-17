<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Schema;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * Rewrites a Schema reference to the one spelling that names its Schema.
 *
 * A Schema of this wiki is identified by its page name (ADR 17), and MediaWiki resolves several
 * spellings to one page: `person` and `Person` are the same page, as are `Person_name` and
 * `Person name`. A reference is therefore not yet an identifier when it arrives; this turns it
 * into one, so that everything downstream — graph labels, RDF classes, Mapping lookups, relation
 * target comparison — reads one name per Schema.
 *
 * A reference to another Source is returned unchanged: that Source names its own Schemas, and
 * this wiki's page-naming rules say nothing about them (ADR 23).
 */
interface SchemaReferenceNormalizer {

	public function normalize( SchemaReference $reference ): SchemaReference;

}
