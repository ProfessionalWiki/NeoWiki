<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall;

use ArrayIterator;
use LogicException;
use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use ProfessionalWiki\NeoWiki\Infrastructure\SearchIndexUpdater;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use stdClass;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IMaintainableDatabase;

/**
 * Takes the subject slot out of every revision, current, old and deleted, as if the revision had never
 * held Subjects. MediaWiki cannot render a revision whose content model no extension provides, so without
 * this a wiki that removes NeoWiki fails on every page that ever held a Subject.
 *
 * The content the slots held is left in place, unreferenced, as MediaWiki's own deleteArchivedRevisions.php
 * leaves the content of the revisions it removes.
 */
class SubjectSlotRemover {

	private ?bool $revisionsHaveSha1 = null;

	public function __construct(
		private readonly IMaintainableDatabase $db,
		private readonly NameTableStore $slotRoleStore,
		private readonly RevisionStore $revisionStore,
		private readonly HTMLCacheUpdater $htmlCacheUpdater,
		private readonly WANObjectCache $wanCache,
		private readonly int $batchSize,
	) {
	}

	/**
	 * @return iterable<int> How many revisions have lost the slot so far, once per batch.
	 */
	public function remove(): iterable {
		$roleId = $this->findSubjectRoleId();

		if ( $roleId === null ) {
			return;
		}

		$lastRevisionId = 0;
		$removed = 0;

		do {
			$revisionIds = $this->subjectRevisionIdsAfter( $roleId, $lastRevisionId );

			if ( $revisionIds === [] ) {
				return;
			}

			$lastRevisionId = max( $revisionIds );

			$this->db->startAtomic( __METHOD__ );
			$liveRevisions = $this->liveRevisionsOf( $revisionIds );
			$this->deleteSlots( $roleId, $revisionIds );
			$this->describeRemainingSlots( $revisionIds, $liveRevisions );
			$this->touchPages( self::currentRevisionsAmong( $liveRevisions ) );
			$this->db->endAtomic( __METHOD__ );

			$this->forgetCachedRevisions( $liveRevisions );
			$this->refreshPagesElsewhere( self::currentRevisionsAmong( $liveRevisions ) );

			$removed += count( $revisionIds );
			yield $removed;
		} while ( count( $revisionIds ) === $this->batchSize );
	}

	/**
	 * Null on a wiki where no revision has ever held Subjects.
	 */
	private function findSubjectRoleId(): ?int {
		try {
			return $this->slotRoleStore->getId( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( NameTableAccessException ) {
			return null;
		}
	}

	/**
	 * @return int[]
	 */
	private function subjectRevisionIdsAfter( int $roleId, int $lastRevisionId ): array {
		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'slot_revision_id' )
			->from( 'slots' )
			->where( [ 'slot_role_id' => $roleId ] )
			->andWhere( $this->db->buildComparison( '>', [ 'slot_revision_id' => $lastRevisionId ] ) )
			->orderBy( 'slot_revision_id' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	/**
	 * The revisions of pages that exist, with their page. The others are deleted, in the archive.
	 *
	 * @param int[] $revisionIds
	 * @return array<int, stdClass> Keyed by revision id
	 */
	private function liveRevisionsOf( array $revisionIds ): array {
		$rows = $this->db->newSelectQueryBuilder()
			->select( [ 'rev_id', 'page_id', 'page_namespace', 'page_title', 'page_latest' ] )
			->from( 'revision' )
			->join( 'page', null, 'rev_page = page_id' )
			->where( [ 'rev_id' => $revisionIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$liveRevisions = [];

		/** @var stdClass $row */
		foreach ( $rows as $row ) {
			$liveRevisions[(int)$row->rev_id] = $row;
		}

		return $liveRevisions;
	}

	/**
	 * @param array<int, stdClass> $liveRevisions
	 * @return stdClass[]
	 */
	private static function currentRevisionsAmong( array $liveRevisions ): array {
		return array_filter(
			$liveRevisions,
			static fn ( stdClass $row ): bool => (int)$row->rev_id === (int)$row->page_latest
		);
	}

	/**
	 * @param int[] $revisionIds
	 */
	private function deleteSlots( int $roleId, array $revisionIds ): void {
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( 'slots' )
			->where( [ 'slot_role_id' => $roleId, 'slot_revision_id' => $revisionIds ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * A revision's size covers all of its slots, and up to MediaWiki 1.45 so does the checksum it stores,
	 * which MediaWiki compares to tell a revert or a null edit. MediaWiki 1.46 dropped that column and
	 * computes the checksum from the slots instead.
	 *
	 * @param int[] $revisionIds
	 * @param array<int, stdClass> $liveRevisions
	 */
	private function describeRemainingSlots( array $revisionIds, array $liveRevisions ): void {
		foreach ( $this->remainingSlotsOf( $revisionIds ) as $revisionId => $slots ) {
			if ( array_key_exists( $revisionId, $liveRevisions ) ) {
				$this->updateRows( 'revision', $this->description( 'rev', $slots ), [ 'rev_id' => $revisionId ] );
			} else {
				$this->updateRows( 'archive', $this->description( 'ar', $slots ), [ 'ar_rev_id' => $revisionId ] );
			}
		}
	}

	/**
	 * @return array<string, string|int>
	 */
	private function description( string $prefix, RevisionSlots $slots ): array {
		$description = [ "{$prefix}_len" => $slots->computeSize() ];

		if ( $this->revisionsHaveSha1() ) {
			$description["{$prefix}_sha1"] = $slots->computeSha1();
		}

		return $description;
	}

	private function revisionsHaveSha1(): bool {
		$this->revisionsHaveSha1 ??= $this->db->fieldExists( 'revision', 'rev_sha1', __METHOD__ );
		return $this->revisionsHaveSha1;
	}

	/**
	 * @param int[] $revisionIds
	 * @return array<int, RevisionSlots>
	 */
	private function remainingSlotsOf( array $revisionIds ): array {
		$rows = $this->db->newSelectQueryBuilder()
			->queryInfo( $this->revisionStore->getSlotsQueryInfo( [ 'content', 'model', 'role' ] ) )
			->where( [ 'slot_revision_id' => $revisionIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$slotRecords = [];

		/** @var stdClass $row */
		foreach ( $rows as $row ) {
			$slotRecords[(int)$row->slot_revision_id][] = self::newSlotRecord( $row );
		}

		return array_map( static fn ( array $records ): RevisionSlots => new RevisionSlots( $records ), $slotRecords );
	}

	/**
	 * Checksum and size are on the row, so the content itself is never loaded.
	 */
	private static function newSlotRecord( stdClass $row ): SlotRecord {
		return new SlotRecord(
			$row,
			static function (): never {
				throw new LogicException( 'Describing a revision needs no slot content' );
			}
		);
	}

	private function updateRows( string $table, array $set, array $conditions ): void {
		$this->db->newUpdateQueryBuilder()
			->update( $table )
			->set( $set )
			->where( $conditions )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * In the same transaction as the slot removal, so that an interrupted run cannot leave a page rendered
	 * from the Subjects it no longer holds: a later run no longer finds the page.
	 *
	 * @param stdClass[] $currentRevisions
	 */
	private function touchPages( array $currentRevisions ): void {
		if ( $currentRevisions === [] ) {
			return;
		}

		$this->updateRows(
			'page',
			[ 'page_touched' => $this->db->timestamp() ],
			[ 'page_id' => array_map( static fn ( stdClass $row ): int => (int)$row->page_id, array_values( $currentRevisions ) ) ]
		);
	}

	/**
	 * Mirrors the WAN cache entries RevisionStore fills, so that no later request is handed a revision that
	 * still lists the slot or a size that still covers it. A web server's local cache is out of reach from
	 * here, which is why uninstalling ends with restarting PHP.
	 *
	 * @param array<int, stdClass> $liveRevisions
	 */
	private function forgetCachedRevisions( array $liveRevisions ): void {
		foreach ( $liveRevisions as $row ) {
			if ( (int)$row->page_namespace === NS_TEMPLATE ) {
				$this->forgetTemplateSlots( $row );
			}

			if ( (int)$row->rev_id === (int)$row->page_latest ) {
				$this->forgetCurrentRevision( $row );
			}
		}
	}

	/**
	 * RevisionStore keys these by the local wiki id, false, which makes the same key as an empty string.
	 */
	private function forgetTemplateSlots( stdClass $row ): void {
		$this->wanCache->delete(
			$this->wanCache->makeKey( 'revision-slots', '', (int)$row->page_id, (int)$row->rev_id )
		);
	}

	private function forgetCurrentRevision( stdClass $row ): void {
		$this->wanCache->delete( $this->wanCache->makeGlobalKey(
			RevisionStore::ROW_CACHE_KEY,
			$this->db->getDomainID(),
			(int)$row->page_id,
			(int)$row->rev_id
		) );
	}

	/**
	 * Out of the CDN, and out of search results for the Subjects they held: the wiki's search engine
	 * indexes a page's Subjects with its text.
	 *
	 * @param stdClass[] $currentRevisions
	 */
	private function refreshPagesElsewhere( array $currentRevisions ): void {
		$this->htmlCacheUpdater->purgeTitleUrls(
			new ArrayIterator( array_map(
				static fn ( stdClass $row ): PageReferenceValue => PageReferenceValue::localReference(
					(int)$row->page_namespace,
					$row->page_title
				),
				array_values( $currentRevisions )
			) ),
			HTMLCacheUpdater::PURGE_INTENT_TXROUND_REFLECTED
		);

		foreach ( $currentRevisions as $row ) {
			$revision = $this->revisionStore->getRevisionById( (int)$row->rev_id, IDBAccessObject::READ_LATEST );

			if ( $revision !== null ) {
				SearchIndexUpdater::updateFor( $revision );
			}
		}
	}

}
