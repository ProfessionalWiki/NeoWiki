<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;

interface MappingNameLookup {

	/**
	 * The names of every Mapping page, used to enumerate all Mappings.
	 *
	 * @return MappingName[]
	 */
	public function getMappingNames(): array;

}
