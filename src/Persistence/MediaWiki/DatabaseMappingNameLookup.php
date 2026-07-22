<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use Wikimedia\Rdbms\IDatabase;

class DatabaseMappingNameLookup implements MappingNameLookup {

	private const READABLE_NAMES_BATCH_SIZE = 100;

	public function __construct(
		private readonly IDatabase $db,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly TitleFactory $titleFactory,
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

	/**
	 * Keyset pagination over page_id, mirroring the Schema and Layout name lookups: each batch
	 * seeks past the last seen page ID, an unreadable Mapping is never yielded, and a cursor built
	 * from the yielded keys pages over readable Mappings only (#1062). getMappingNames stays
	 * unfiltered for the system-context RDF-projection and DumpRdf path, mirroring the split
	 * between {@see NeoWikiExtension::getRdfProjectionNames} and
	 * NeoWikiExtension::filterReadableProjectionNames. A page whose title is not a usable Mapping
	 * name is skipped like in getMappingNames.
	 *
	 * @return iterable<int, MappingName> Readable Mapping names keyed by page ID, in page-ID order.
	 */
	public function getReadableMappingNames( int $afterPageId = 0 ): iterable {
		$lastPageId = $afterPageId;

		do {
			$res = $this->db->newSelectQueryBuilder()
				->select( [ 'page_id', 'page_title' ] )
				->from( 'page' )
				->where( [
					'page_namespace' => NeoWikiExtension::NS_MAPPING,
					$this->db->expr( 'page_id', '>', $lastPageId ),
				] )
				->orderBy( 'page_id ASC' )
				->limit( self::READABLE_NAMES_BATCH_SIZE )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				$lastPageId = (int)$row->page_id;

				try {
					$name = new MappingName( str_replace( '_', ' ', $row->page_title ) );
				} catch ( InvalidArgumentException ) {
					continue;
				}

				$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_MAPPING );

				if ( $title !== null && $this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
					yield $lastPageId => $name;
				}
			}
		} while ( $res->numRows() === self::READABLE_NAMES_BATCH_SIZE );
	}

}
