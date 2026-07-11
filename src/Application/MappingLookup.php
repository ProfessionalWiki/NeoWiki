<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;

interface MappingLookup {

	public function getMapping( MappingName $name ): ?Mapping;

	/**
	 * Every valid Mapping across all Mapping pages. Structurally invalid Mapping pages are omitted.
	 * Used to select the Mappings for a projection target and to detect duplicate (Schema, target) pairs.
	 *
	 * @return Mapping[]
	 */
	public function getAllMappings(): array;

}
