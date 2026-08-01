<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Stores rebuild runs in the `neowiki_rebuild_runs` table.
 *
 * Reads go to the primary database, not a replica: a run's own progress updates must be visible to the
 * batch that follows them, and the check for a concurrent run must see one started moments ago.
 */
class DatabaseRebuildRunRepository implements RebuildRunRepository {

	private const TABLE = 'neowiki_rebuild_runs';

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function startRun( string $store, RebuildTrigger $trigger ): RebuildRun {
		$db = $this->connectionProvider->getPrimaryDatabase();

		$db->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( [
				'nwrr_store' => $store,
				'nwrr_status' => RebuildStatus::Running->value,
				'nwrr_cursor' => 0,
				'nwrr_processed' => 0,
				'nwrr_failed' => 0,
				'nwrr_trigger' => $trigger->value,
				'nwrr_started' => $db->timestamp(),
				'nwrr_finished' => null,
				'nwrr_error' => null,
			] )
			->caller( __METHOD__ )
			->execute();

		return new RebuildRun(
			id: $db->insertId(),
			store: $store,
			status: RebuildStatus::Running,
			cursor: 0,
			processed: 0,
			failed: 0,
			trigger: $trigger,
		);
	}

	/**
	 * The finish time follows from the status rather than being passed in, so a run cannot be stored as
	 * still going yet finished, or as ended without saying when. Reopening a terminal run clears it.
	 */
	public function updateRun( RebuildRun $run ): void {
		$db = $this->connectionProvider->getPrimaryDatabase();

		$db->newUpdateQueryBuilder()
			->update( self::TABLE )
			->set( [
				'nwrr_status' => $run->status->value,
				'nwrr_cursor' => $run->cursor,
				'nwrr_processed' => $run->processed,
				'nwrr_failed' => $run->failed,
				'nwrr_finished' => $run->status->isTerminal() ? $db->timestamp() : null,
				'nwrr_error' => $run->error,
			] )
			->where( [ 'nwrr_id' => $run->id ] )
			->caller( __METHOD__ )
			->execute();
	}

	public function getActiveRun( string $store ): ?RebuildRun {
		return $this->getMostRecentRun( [
			'nwrr_store' => $store,
			'nwrr_status' => RebuildStatus::Running->value,
		] );
	}

	public function getLatestRun( string $store ): ?RebuildRun {
		return $this->getMostRecentRun( [ 'nwrr_store' => $store ] );
	}

	/**
	 * Ties on the start time are broken by id, so concurrent starts within one timestamp second still
	 * order deterministically, and "the latest run" is always one run.
	 *
	 * @param array<string, string> $conditions
	 */
	private function getMostRecentRun( array $conditions ): ?RebuildRun {
		$row = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( self::TABLE )
			->where( $conditions )
			->orderBy( [ 'nwrr_started', 'nwrr_id' ], 'DESC' )
			->caller( __METHOD__ )
			->fetchRow();

		return $row === false ? null : self::newRunFromRow( $row );
	}

	private function getDb(): IReadableDatabase {
		return $this->connectionProvider->getPrimaryDatabase();
	}

	private static function newRunFromRow( stdClass $row ): RebuildRun {
		return new RebuildRun(
			id: (int)$row->nwrr_id,
			store: (string)$row->nwrr_store,
			status: RebuildStatus::from( (string)$row->nwrr_status ),
			cursor: (int)$row->nwrr_cursor,
			processed: (int)$row->nwrr_processed,
			failed: (int)$row->nwrr_failed,
			trigger: RebuildTrigger::from( (string)$row->nwrr_trigger ),
			error: $row->nwrr_error === null ? null : (string)$row->nwrr_error,
		);
	}

}
