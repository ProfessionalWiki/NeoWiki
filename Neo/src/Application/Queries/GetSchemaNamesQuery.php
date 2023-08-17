<?php

namespace ProfessionalWiki\NeoWiki\Application\Queries;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup\DatabaseSchemaNameLookup;
use SearchEngine;
use Title;

class GetSchemaNamesQuery {
	private const LIMIT = 10;

	public function __construct(
		private readonly DatabaseSchemaNameLookup $schemaNameLookup,
		private readonly SearchEngine $searchEngine
	) {
	}

	public function execute( string $search ): array {
		$schemaNames = $this->getSchemasName( $search );

		return array_map( function ( Title $title ): string {
			return $title->getText();
		}, $schemaNames );
	}

	private function getSchemasName( string $search ): array {
		if ( $search ) {
			$this->searchEngine->setNamespaces( [ (int)NS_NEOWIKI_SCHEMA ] );
			$this->searchEngine->setLimitOffset( $this::LIMIT );

			return $this->searchEngine->extractTitles(
				$this->searchEngine->completionSearch( $search )
			);
		}

		return $this->schemaNameLookup->getFirstTenSchemaNames();
	}
}
