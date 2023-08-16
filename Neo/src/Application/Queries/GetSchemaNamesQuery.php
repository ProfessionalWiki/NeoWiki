<?php

namespace ProfessionalWiki\NeoWiki\Application\Queries;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup\DatabaseSchemaNameLookup;

class GetSchemaNamesQuery {

	public function __construct(
		private readonly DatabaseSchemaNameLookup $schemaNameLookup
	) {
	}

	public function execute( string $search ): array {
		$schemaNames = $this->schemaNameLookup->getFirstTenSchemaNamesMatching( $search );
		return array_map( function ( array $schemaName ): string {
			return (string)$schemaName[ 'page_title' ];
		}, $schemaNames );
	}
}
