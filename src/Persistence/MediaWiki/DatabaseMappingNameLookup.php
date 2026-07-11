<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use Wikimedia\Rdbms\IDatabase;

class DatabaseMappingNameLookup implements MappingNameLookup {

	public function __construct(
		private readonly IDatabase $db,
	) {
	}

	/**
	 * @return MappingName[]
	 */
	public function getMappingNames(): array {
		$titles = $this->db->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( 'page' )
			->where( [ 'page_namespace' => NeoWikiExtension::NS_MAPPING ] )
			->orderBy( 'page_id ASC' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		// The DB key uses underscores; the display text (spaces) matches Title::getText(), so the name
		// compares equal to the page identity a MappingContentHandler sees when detecting duplicates.
		return array_map(
			static fn ( string $title ): MappingName => new MappingName( str_replace( '_', ' ', $title ) ),
			$titles
		);
	}

}
