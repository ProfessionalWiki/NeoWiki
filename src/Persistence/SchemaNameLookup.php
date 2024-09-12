<?php

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence;

use TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search ): array;

}
