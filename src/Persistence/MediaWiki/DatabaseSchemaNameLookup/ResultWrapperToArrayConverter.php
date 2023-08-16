<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

use Wikimedia\Rdbms\IResultWrapper;

class ResultWrapperToArrayConverter {
	public function convertToObjectArray( IResultWrapper $resultWrapper ): array {
		$result = [];

		foreach ( $resultWrapper as $object ) {
			$result[] = (array)$object;
		}

		return $result;
	}
}
