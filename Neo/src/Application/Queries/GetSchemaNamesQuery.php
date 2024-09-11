<?php

namespace ProfessionalWiki\NeoWiki\Application\Queries;

use ProfessionalWiki\NeoWiki\Domain\Subject\SchemaNameLookup;
use TitleValue;

readonly class GetSchemaNamesQuery {

	public function __construct(
		private SchemaNameLookup $schemaNameLookup
	) {
	}

	/**
	 * @return string[]
	 */
	public function execute( string $search ): array {
		return array_map(
			function ( TitleValue $title ): string {
				return $title->getText();
			},
			$this->schemaNameLookup->getSchemaNamesMatching( $search )
		);
	}

}
