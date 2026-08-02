<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Utils\MWTimestamp;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildPhase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Stores rebuild runs in the `neowiki_rebuild_runs` table.
 *
 * Reads go to the primary database, not a replica: a run's own progress updates must be visible to the
 * batch that follows them, a cancellation must be visible to the batch that follows it, and the check
 * for a concurrent run must see one started moments ago.
 */
class DatabaseRebuildRunRepository implements RebuildRunRepository {

	private const TABLE = 'neowiki_rebuild_runs';

	/**
	 * What fits in the error column. A driver quoting the query it choked on can run far longer than
	 * that, and losing the tail of a message beats losing the record of the failure.
	 */
	private const MAX_ERROR_LENGTH = 65530;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function startRun( string $store, RebuildTrigger $trigger, RebuildStatus $status ): RebuildRun {
		$db = $this->connectionProvider->getPrimaryDatabase();
		$started = $db->timestamp();

		$db->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( [
				'nwrr_store' => $store,
				'nwrr_status' => $status->value,
				'nwrr_phase' => RebuildPhase::Pages->value,
				'nwrr_cursor' => 0,
				'nwrr_processed' => 0,
				'nwrr_failed' => 0,
				'nwrr_trigger' => $trigger->value,
				'nwrr_started' => $started,
				'nwrr_finished' => null,
				'nwrr_error' => null,
			] )
			->caller( __METHOD__ )
			->execute();

		return new RebuildRun(
			id: $db->insertId(),
			store: $store,
			status: $status,
			phase: RebuildPhase::Pages,
			cursor: 0,
			processed: 0,
			failed: 0,
			trigger: $trigger,
			started: self::asMediaWikiTimestamp( $started ),
		);
	}

	/**
	 * The finish time follows from the status rather than being passed in, so a run cannot be stored as
	 * still going yet finished, or as ended without saying when. Picking a terminal run back up clears it.
	 */
	public function updateRun( RebuildRun $run ): void {
		$this->write( $run, [ 'nwrr_id' => $run->id ] );
	}

	/**
	 * The status is part of what the write is conditional on, so a run something else has ended is left
	 * as that something else left it.
	 *
	 * Whether it landed is taken from the row count the write matched, not read back: these connections
	 * ask their server for matched rows rather than changed ones, so a write that changed nothing still
	 * counts — and a read-back is served from the reading transaction's own snapshot, which a job runner
	 * opens before the batch and which therefore predates the very cancellation this exists to notice.
	 * That is also why what ended the run is read under a lock: a locking read sees the latest committed
	 * row rather than the snapshot's.
	 */
	public function updateRunWhileActive( RebuildRun $run ): ?RebuildRun {
		$matchedRows = $this->write( $run, [
			'nwrr_id' => $run->id,
			'nwrr_status' => [ RebuildStatus::Queued->value, RebuildStatus::Running->value ],
		] );

		return $matchedRows > 0 ? $run : $this->getMostRecentRunUnderLock( [ 'nwrr_id' => $run->id ] );
	}

	/**
	 * @param array<string, string|string[]|int> $conditions
	 *
	 * @return int How many rows the write matched.
	 */
	private function write( RebuildRun $run, array $conditions ): int {
		$db = $this->connectionProvider->getPrimaryDatabase();

		$db->newUpdateQueryBuilder()
			->update( self::TABLE )
			->set( [
				'nwrr_status' => $run->status->value,
				'nwrr_phase' => $run->phase->value,
				'nwrr_cursor' => $run->cursor,
				'nwrr_processed' => $run->processed,
				'nwrr_failed' => $run->failed,
				'nwrr_finished' => $run->status->isTerminal() ? $db->timestamp() : null,
				'nwrr_error' => $run->error === null ? null : mb_strcut( $run->error, 0, self::MAX_ERROR_LENGTH ),
			] )
			->where( $conditions )
			->caller( __METHOD__ )
			->execute();

		return $db->affectedRows();
	}

	public function getRun( int $id ): ?RebuildRun {
		return $this->getMostRecentRun( [ 'nwrr_id' => $id ] );
	}

	public function getActiveRun( string $store ): ?RebuildRun {
		return $this->getMostRecentRun( [
			'nwrr_store' => $store,
			'nwrr_status' => [ RebuildStatus::Queued->value, RebuildStatus::Running->value ],
		] );
	}

	public function cancelActiveRun( string $store ): ?RebuildRun {
		$db = $this->connectionProvider->getPrimaryDatabase();

		$db->newUpdateQueryBuilder()
			->update( self::TABLE )
			->set( [
				'nwrr_status' => RebuildStatus::Cancelled->value,
				'nwrr_finished' => $db->timestamp(),
				'nwrr_error' => null,
			] )
			->where( [
				'nwrr_store' => $store,
				'nwrr_status' => [ RebuildStatus::Queued->value, RebuildStatus::Running->value ],
			] )
			->caller( __METHOD__ )
			->execute();

		if ( $db->affectedRows() === 0 ) {
			return null;
		}

		// The store's latest run is the one just cancelled: a store has at most one queued or running run,
		// and it is always the last one started.
		return $this->getMostRecentRunUnderLock( [ 'nwrr_store' => $store ] );
	}

	public function getLatestRun( string $store ): ?RebuildRun {
		return $this->getMostRecentRun( [ 'nwrr_store' => $store ] );
	}

	public function getLastSuccessfulRun( string $store ): ?RebuildRun {
		return $this->getMostRecentRun( [
			'nwrr_store' => $store,
			'nwrr_status' => RebuildStatus::Succeeded->value,
		] );
	}

	/**
	 * Ties on the start time are broken by id, so concurrent starts within one timestamp second still
	 * order deterministically, and "the latest run" is always one run.
	 *
	 * @param array<string, string|string[]|int> $conditions
	 */
	private function getMostRecentRun( array $conditions ): ?RebuildRun {
		return self::newRunFromResult( $this->newRunQuery( $conditions )->fetchRow() );
	}

	/**
	 * The run as the database now holds it, rather than as the reading transaction's snapshot has it. Only
	 * for reading back a row a write has just settled the fate of, which is where the snapshot is by
	 * definition out of date. Every other read here is of this process's own run and is happy with it.
	 *
	 * @param array<string, string|string[]|int> $conditions
	 */
	private function getMostRecentRunUnderLock( array $conditions ): ?RebuildRun {
		return self::newRunFromResult( $this->newRunQuery( $conditions )->forUpdate()->fetchRow() );
	}

	/**
	 * @param array<string, string|string[]|int> $conditions
	 */
	private function newRunQuery( array $conditions ): SelectQueryBuilder {
		return $this->connectionProvider->getPrimaryDatabase()->newSelectQueryBuilder()
			->select( '*' )
			->from( self::TABLE )
			->where( $conditions )
			->orderBy( [ 'nwrr_started', 'nwrr_id' ], 'DESC' )
			->caller( __METHOD__ );
	}

	private static function newRunFromResult( stdClass|bool $row ): ?RebuildRun {
		return $row instanceof stdClass ? self::newRunFromRow( $row ) : null;
	}

	/**
	 * A row whose status, phase or trigger this version does not recognise — written by a newer one, or
	 * edited by hand — reads as no run at all, rather than throwing from wherever a rebuild happens to
	 * ask. The cost is that a rebuild may be started alongside such a row; the cost of the alternative is
	 * a fatal in the middle of one.
	 */
	private static function newRunFromRow( stdClass $row ): ?RebuildRun {
		$status = RebuildStatus::tryFrom( (string)$row->nwrr_status );
		$phase = RebuildPhase::tryFrom( (string)$row->nwrr_phase );
		$trigger = RebuildTrigger::tryFrom( (string)$row->nwrr_trigger );

		if ( $status === null || $phase === null || $trigger === null ) {
			return null;
		}

		return new RebuildRun(
			id: (int)$row->nwrr_id,
			store: (string)$row->nwrr_store,
			status: $status,
			phase: $phase,
			cursor: (int)$row->nwrr_cursor,
			processed: (int)$row->nwrr_processed,
			failed: (int)$row->nwrr_failed,
			trigger: $trigger,
			error: $row->nwrr_error === null ? null : (string)$row->nwrr_error,
			started: self::asMediaWikiTimestamp( $row->nwrr_started ),
			finished: self::asMediaWikiTimestamp( $row->nwrr_finished ),
		);
	}

	/**
	 * Timestamp columns come back in the database's own format — Postgres hands back something quite
	 * unlike the MediaWiki timestamps every comparison and every display here is written against.
	 */
	private static function asMediaWikiTimestamp( mixed $timestamp ): ?string {
		if ( !is_scalar( $timestamp ) ) {
			return null;
		}

		$converted = MWTimestamp::convert( TS_MW, (string)$timestamp );

		return $converted === false ? null : $converted;
	}

}
