<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
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

		$names = [];

		foreach ( $titles as $title ) {
			// The DB key uses underscores; the display text (spaces) matches Title::getText(), so each name
			// is the projection/target name a caller passes to ?projection= or --projection.
			try {
				$names[] = new MappingName( str_replace( '_', ' ', $title ) );
			} catch ( InvalidArgumentException ) {
				// A page whose title is not a usable Mapping name — the reserved "native" — is not a
				// projection, so it is skipped, mirroring how the deserializer skips a malformed entry.
				// Saving such a page is already rejected; only an import or a move, which bypass that
				// check, can leave one in the namespace, and it must not break the projection listing.
			}
		}

		return $names;
	}

}
