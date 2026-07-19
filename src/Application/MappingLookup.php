<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;

interface MappingLookup {

	/**
	 * The Mapping stored on the page of the given target/projection name, or null when the page does not
	 * exist, is unreadable, or is structurally invalid.
	 */
	public function getMapping( MappingName $name ): ?Mapping;

}
