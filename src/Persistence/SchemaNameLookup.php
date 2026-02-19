<?php

namespace ProfessionalWiki\NeoWiki\Persistence;

use TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit = 10, int $offset = 0 ): array;

	public function getSchemaCount(): int;

}
