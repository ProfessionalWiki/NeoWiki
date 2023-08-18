<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

use Title;

interface SchemaNameLookup {

	/**
	 * @return Title[]
	 */
	public function getSchemaNamesMatching( string $search ): array;

}
