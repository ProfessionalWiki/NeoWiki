<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

interface SchemaReferenceResolver {

	/**
	 * Null when the reference resolves to no Schema the caller may read.
	 */
	public function getSchema( SchemaReference $reference ): ?Schema;

}
