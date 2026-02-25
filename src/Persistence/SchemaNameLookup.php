<?php

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\Title\TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit, int $offset = 0 ): array;

	public function getSchemaCount(): int;

}
