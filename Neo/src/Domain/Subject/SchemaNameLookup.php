<?php

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search ): array;

}
