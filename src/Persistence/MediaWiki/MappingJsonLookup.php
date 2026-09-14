<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;

/**
 * The stored JSON of a Mapping page, before it is parsed into a Mapping.
 */
interface MappingJsonLookup {

	/**
	 * Null when the page's content could not be read.
	 */
	public function getMappingJson( MappingName $name ): ?string;

}
