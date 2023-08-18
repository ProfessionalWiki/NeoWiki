<?php

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use Title;

interface SchemaNameLookup {

	/**
	 * @return Title[]
	 */
	public function getSchemaNamesMatching( string $search ): array;

}
