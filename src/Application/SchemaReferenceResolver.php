<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * Resolves a SchemaReference to its Schema through the reference's own Source (ADR 23), or null when
 * it cannot be resolved. The name-parameterized {@see SchemaLookup} stays the local, name-keyed seam;
 * this one is for schema references carried by Subjects, whose source may differ from the subject's.
 */
interface SchemaReferenceResolver {

	public function resolve( SchemaReference $reference ): ?Schema;

}
