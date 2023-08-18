<?php

namespace ProfessionalWiki\NeoWiki\Application\Queries;

use ProfessionalWiki\NeoWiki\Domain\Subject\SchemaNameLookup;
use Title;

class GetSchemaNamesQuery {

	public function __construct(
		private readonly SchemaNameLookup $schemaNameLookup
	) {
	}

	public function execute( string $search ): array {
		$schemaNames = $this->schemaNameLookup->getSchemaNamesMatching( $search );

		return array_map(
			function ( Title $title ): string {
				return $title->getText();
			},
			$schemaNames
		);
	}

}
