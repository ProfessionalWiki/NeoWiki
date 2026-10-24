<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Page\WikiPageFactory;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\PageDeleter;
use Wikimedia\Rdbms\IDatabase;

/**
 * Removes every page in NeoWiki's namespaces, with its history. Without NeoWiki those namespaces are not
 * defined, which leaves their pages unreachable, and MediaWiki cannot render the Schema, Layout and Mapping
 * content they hold.
 */
class NeoWikiNamespacePurger {

	public const array NAMESPACES = [
		NeoWikiExtension::NS_SCHEMA,
		NeoWikiExtension::NS_SCHEMA + 1,
		NeoWikiExtension::NS_LAYOUT,
		NeoWikiExtension::NS_LAYOUT + 1,
		NeoWikiExtension::NS_MAPPING,
		NeoWikiExtension::NS_MAPPING + 1,
		NeoWikiExtension::NS_SUBJECT,
		NeoWikiExtension::NS_SUBJECT + 1,
	];

	private const string DELETION_REASON = 'Uninstalling NeoWiki';

	public function __construct(
		private readonly IDatabase $db,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly PageDeleter $pageDeleter,
		private readonly int $batchSize,
	) {
	}

	/**
	 * Deleted the way an administrator would, so that what MediaWiki keeps about a page beside its
	 * revisions, such as its links and its search index entry, goes with it, and the deletion log
	 * records it. A page that cannot be deleted is passed over.
	 *
	 * The updates a deletion defers, NeoWiki's own among them, run after each batch, so that none is left
	 * to run after the tables it writes to are gone.
	 *
	 * @param callable(string, string): void $onFailure Called with the title of each page that could not be
	 *   deleted, as written in wikitext, and the reason
	 * @return iterable<int> How many pages have been deleted so far, once per batch.
	 */
	public function deletePages( callable $onFailure ): iterable {
		$lastPageId = 0;
		$deleted = 0;

		do {
			$pageIds = $this->pageIdsAfter( $lastPageId );

			if ( $pageIds === [] ) {
				return;
			}

			$lastPageId = max( $pageIds );

			foreach ( $pageIds as $pageId ) {
				$deleted += $this->deletePage( $pageId, $onFailure ) ? 1 : 0;
			}

			DeferredUpdates::doUpdates();

			yield $deleted;
		} while ( count( $pageIds ) === $this->batchSize );
	}

	/**
	 * @return int[]
	 */
	private function pageIdsAfter( int $lastPageId ): array {
		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->where( [ 'page_namespace' => self::NAMESPACES ] )
			->andWhere( $this->db->buildComparison( '>', [ 'page_id' => $lastPageId ] ) )
			->orderBy( 'page_id' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	/**
	 * A page that is gone by the time it is reached needs no deleting.
	 *
	 * @param callable(string, string): void $onFailure
	 */
	private function deletePage( int $pageId, callable $onFailure ): bool {
		$page = $this->wikiPageFactory->newFromID( $pageId );

		if ( $page === null ) {
			return false;
		}

		$status = $this->pageDeleter->deletePage( $page, self::DELETION_REASON );

		if ( !$status->succeeded ) {
			$onFailure( $page->getTitle()->getPrefixedText(), $status->errorMessage ?? '' );
		}

		return $status->succeeded;
	}

	/**
	 * The slots of the purged revisions stay, as MediaWiki's own deleteArchivedRevisions.php leaves them:
	 * MediaWiki reads the highest revision id from them as well as from the archive, so that it never hands
	 * out the id of a revision it has purged.
	 *
	 * @return iterable<int> How many deleted revisions have been purged so far, once per batch.
	 */
	public function purgeDeletedRevisions(): iterable {
		$lastArchiveId = 0;
		$purged = 0;

		do {
			$archiveIds = $this->archiveIdsAfter( $lastArchiveId );

			if ( $archiveIds === [] ) {
				return;
			}

			$lastArchiveId = max( $archiveIds );

			$this->db->newDeleteQueryBuilder()
				->deleteFrom( 'archive' )
				->where( [ 'ar_id' => $archiveIds ] )
				->caller( __METHOD__ )
				->execute();

			$purged += count( $archiveIds );
			yield $purged;
		} while ( count( $archiveIds ) === $this->batchSize );
	}

	/**
	 * @return int[]
	 */
	private function archiveIdsAfter( int $lastArchiveId ): array {
		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'ar_id' )
			->from( 'archive' )
			->where( [ 'ar_namespace' => self::NAMESPACES ] )
			->andWhere( $this->db->buildComparison( '>', [ 'ar_id' => $lastArchiveId ] ) )
			->orderBy( 'ar_id' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

}
