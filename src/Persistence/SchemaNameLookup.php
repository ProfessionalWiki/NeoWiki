<?php

namespace ProfessionalWiki\NeoWiki\Persistence;

use TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search ): array;

}
