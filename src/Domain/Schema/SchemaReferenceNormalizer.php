<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

/**
 * Rewrites a Schema reference to the one spelling that names its Schema.
 *
 * A Schema of this wiki is identified by its page name (ADR 17), and MediaWiki resolves several
 * spellings to one page: `person` and `Person` are the same page, as are `Person_name` and
 * `Person name`. A reference is therefore not yet an identifier when it arrives; this turns it into
 * one, so that a Subject's Schema reads the same wherever it is used — the graph label it takes, the
 * RDF class it is given, the Mapping entry it is looked up under.
 *
 * A reference to another Source is returned unchanged: that Source names its own Schemas, and
 * this wiki's page-naming rules say nothing about them (ADR 23).
 */
interface SchemaReferenceNormalizer {

	public function normalize( SchemaReference $reference ): SchemaReference;

}
