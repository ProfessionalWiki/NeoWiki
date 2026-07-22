<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\LayoutNameLookup;
use Wikimedia\Rdbms\IDatabase;

class DatabaseLayoutNameLookup implements LayoutNameLookup {

	private const READABLE_NAMES_BATCH_SIZE = 100;

	public function __construct(
		private readonly IDatabase $db,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly TitleFactory $titleFactory,
	) {
	}

	/**
	 * Keyset pagination over page_id: each batch seeks past the last seen page ID instead of
	 * re-walking the namespace, so a request costs the batches it consumes, not the whole
	 * namespace, and pages stay stable when Layouts are created or deleted between requests.
	 * The raw DB query applies no visibility rules, so the per-title read check here is the sole
	 * read gate on the Layout listing; an unreadable Layout is never yielded and a cursor built
	 * from the yielded keys pages over readable Layouts only (#1062).
	 *
	 * @return iterable<int, TitleValue> Readable Layout names keyed by page ID, in page-ID order.
	 */
	public function getReadableLayoutNames( int $afterPageId = 0 ): iterable {
		$lastPageId = $afterPageId;

		do {
			$res = $this->db->select(
				'page',
				[ 'page_id', 'page_title' ],
				[
					'page_namespace' => NeoWikiExtension::NS_LAYOUT,
					$this->db->expr( 'page_id', '>', $lastPageId ),
				],
				__METHOD__,
				[
					'ORDER BY' => 'page_id ASC',
					'LIMIT' => self::READABLE_NAMES_BATCH_SIZE,
				]
			);

			foreach ( $res as $row ) {
				$lastPageId = (int)$row->page_id;
				$title = new TitleValue( NeoWikiExtension::NS_LAYOUT, $row->page_title );

				if ( $this->readAuthorizer->authorizeReadByPageTitle( $this->titleFactory->newFromLinkTarget( $title ) ) ) {
					yield $lastPageId => $title;
				}
			}
		} while ( $res->numRows() === self::READABLE_NAMES_BATCH_SIZE );
	}

}
