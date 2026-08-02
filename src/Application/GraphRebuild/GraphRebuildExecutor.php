<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use Exception;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildPhase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\DeletedSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIdsLookup;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Walks the wiki for one rebuild run, projecting it into that run's store.
 *
 * A rebuild has two phases. It reprojects every page that carries a Subject, then removes the pages
 * MediaWiki no longer has — re-saving what still exists cannot undo a projection delete that failed, so
 * a page deleted while the store was unreachable would otherwise stay queryable for good. Deleting a
 * page that is already absent is a no-op, so repeating part of the second phase is safe.
 *
 * Both phases run in batches, recording the phase and the position within it after each one. That is
 * what makes a rebuild resumable — including one interrupted between the phases — and it bounds the
 * memory the walk over the wiki needs. A batch is all-or-nothing as progress: one that ends early
 * records nothing, so a resumed run does it again. Projecting a page replaces what the store holds for
 * it, so doing that is harmless.
 *
 * A batch is the unit of work, and {@see self::executeOneBatch()} is one. Running a rebuild here and now
 * ({@see self::execute()}) is that step in a loop; running it in the background is that step once per
 * job. Both re-read the run before the batch, so a run cancelled from anywhere stops at the next batch
 * boundary whatever is driving it.
 *
 * Failures are separated by what they say about the rest of the run:
 *
 * - A page that fails to project or to be removed is logged and counted, and the run carries on. One
 *   unreadable page must not cost the wiki its whole rebuild.
 * - A store that will not open — one whose initialize() throws — ends the run before a page is read.
 *   Continuing would only pile up one failure per page in the wiki, and the recorded cursor would then
 *   be worthless for resuming.
 * - A store that dies mid-walk is recognised by every page of a batch failing: nothing about a store
 *   that is answering makes a whole batch of unrelated pages fail at once. The run ends with its cursor
 *   left at the batch it was reading, so resuming retries that batch rather than skipping it as settled.
 *   A batch of one page is exempt, because there a whole-batch failure says nothing about the store —
 *   and treating it as a store failure would leave `--resume` retrying the same broken page for ever.
 * - A request timeout or a wiki-database error ends the run: the pages after this one would fail
 *   identically.
 */
class GraphRebuildExecutor {

	/**
	 * Below this, a batch failing entirely is not evidence of anything about the store.
	 */
	private const int MIN_BATCH_SIZE_FOR_STORE_DEATH = 2;

	public function __construct(
		private readonly SubjectPageIdsLookup $subjectPageIds,
		private readonly DeletedSubjectPageIdsLookup $deletedSubjectPageIds,
		private readonly RebuildRunRepository $runs,
		private readonly TitleFactory $titleFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Rebuilds until the run reaches a terminal status, batch after batch, and returns it as it ended.
	 *
	 * @param int<1, max> $batchSize
	 */
	public function execute(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		SubjectPageRebuilder $pageRebuilder,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		try {
			$store->initialize();

			do {
				$run = $this->runBatch( $run, $store, $pageRebuilder, $batchSize, $observer );
			} while ( !$run->status->isTerminal() );
		} catch ( Throwable $e ) {
			// Broader than the Exception boundary elsewhere: a run left recorded as still going blocks
			// every later rebuild of that store, so even a programming bug has to be recorded as its end.
			return $this->failRun( $run, BackendFailureMessage::withoutCredentials( $e->getMessage() ), $e );
		}

		return $run;
	}

	/**
	 * Advances the run by one batch and returns it as it now stands, terminal or not. The background
	 * path's unit of work: whatever drives it decides whether to queue the next one.
	 *
	 * The store is opened here rather than once per run, because each background batch runs in a process
	 * of its own that has opened nothing. Opening a store is idempotent and is what the plugin contract
	 * asks callers to be free to repeat.
	 *
	 * @param int<1, max> $batchSize
	 */
	public function executeOneBatch(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		SubjectPageRebuilder $pageRebuilder,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		try {
			$store->initialize();

			return $this->runBatch( $run, $store, $pageRebuilder, $batchSize, $observer );
		} catch ( Throwable $e ) {
			return $this->failRun( $run, BackendFailureMessage::withoutCredentials( $e->getMessage() ), $e );
		}
	}

	/**
	 * The run as the records now hold it decides what this batch does, not the copy the caller is
	 * holding: that is how a cancellation, or a batch another process already ran, is noticed. A run that
	 * is no longer going is returned untouched, so nothing is written over the status that ended it.
	 *
	 * @param int<1, max> $batchSize
	 */
	private function runBatch(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		SubjectPageRebuilder $pageRebuilder,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		$current = $this->runs->getRun( $run->id );

		if ( $current === null ) {
			throw new RuntimeException(
				'The record of rebuild run ' . $run->id . ' of graph store "' . $run->store
				. '" is gone, so there is nothing left to advance or to record progress on.'
			);
		}

		if ( $current->status->isTerminal() ) {
			return $current;
		}

		if ( $current->status === RebuildStatus::Queued ) {
			// Conditionally, like every other write a batch makes: the copy just read can predate a
			// cancellation, and taking the run up unconditionally would write Running back over it.
			$current = $this->recordRun( $current->started() );

			if ( $current->status->isTerminal() ) {
				return $current;
			}
		}

		return match ( $current->phase ) {
			RebuildPhase::Pages => $this->projectPageBatch( $current, $pageRebuilder, $batchSize, $observer ),
			RebuildPhase::Deletions => $this->removePageBatch( $current, $store, $batchSize, $observer ),
		};
	}

	/**
	 * Records what ended the run both on the run and on the NeoWiki channel: the run keeps the reason an
	 * operator reads back later, and the log keeps the exception class and backtrace the run cannot hold.
	 */
	private function failRun( RebuildRun $run, string $reason, ?Throwable $e ): RebuildRun {
		$failedRun = $this->recordRun( $run->failed( $reason === '' ? null : $reason ) );

		$this->logger->error(
			'NeoWiki graph rebuild of store "' . $failedRun->store . '" ended before reconciling the wiki. '
			. 'Re-run it with --resume to continue from page ' . $failedRun->cursor . '. '
			. 'Underlying error: ' . $reason,
			[ 'exception' => $e, 'store' => $failedRun->store, 'cursor' => $failedRun->cursor ]
		);

		return $failedRun;
	}

	/**
	 * @param int<1, max> $batchSize
	 */
	private function projectPageBatch(
		RebuildRun $run,
		SubjectPageRebuilder $pageRebuilder,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		$pageIds = $this->subjectPageIds->getSubjectPageIdsAfter( $run->cursor, $batchSize );

		if ( $pageIds === [] ) {
			return $this->recordRun( $run->enteredDeletionPhase() );
		}

		$progress = new RebuildProgress( $run );
		$failures = [];
		$offered = 0;

		foreach ( $pageIds as $pageId ) {
			$outcome = $this->projectPage( $pageId, $progress, $pageRebuilder, $observer );

			if ( $outcome->wasOfferedToTheStore ) {
				$offered++;
			}

			if ( $outcome->failure !== null ) {
				$failures[] = $outcome->failure;
			}
		}

		if ( self::wholeBatchFailed( count( $failures ), $offered ) ) {
			$failure = $failures[array_key_last( $failures )];

			// The run rather than its progress, so the cursor stays where this batch began and a resumed
			// run retries it rather than walking past pages nothing is known to be wrong with.
			return $this->failRun( $run, self::describeWholeBatchFailure( $failure ), $failure );
		}

		$updated = $this->recordRun( $progress->applyTo( $run ) );
		$observer->afterPageBatch( $updated, fn (): int => $this->subjectPageIds->countSubjectPages() );

		return $updated;
	}

	/**
	 * Counted over the pages the store was actually offered, not over the batch: a page the walk found and
	 * the wiki has since dropped never reached the store, and letting one of those spare the store its
	 * verdict would walk the whole wiki one failure at a time.
	 */
	private static function wholeBatchFailed( int $failureCount, int $offeredPageCount ): bool {
		return $offeredPageCount >= self::MIN_BATCH_SIZE_FOR_STORE_DEATH && $failureCount === $offeredPageCount;
	}

	private static function describeWholeBatchFailure( Throwable $failure ): string {
		return 'Every page of a batch failed, so the store is treated as gone rather than its pages as '
			. 'unprojectable. Underlying error: '
			. BackendFailureMessage::withoutCredentials( $failure->getMessage() );
	}

	private function projectPage(
		int $pageId,
		RebuildProgress $progress,
		SubjectPageRebuilder $pageRebuilder,
		RebuildBatchObserver $observer
	): ProjectedPageOutcome {
		$title = $this->titleFactory->newFromID( $pageId );

		if ( $title === null ) {
			$this->logSkippedPage( $pageId, 'MediaWiki no longer has it' );
			$progress->pageSkipped( $pageId );
			return ProjectedPageOutcome::skipped();
		}

		try {
			$outcome = $pageRebuilder->rebuild( $title );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logPageFailure( 'project', $pageId, $e );
			$progress->pageFailed( $pageId );
			$observer->pageFailed( $pageId );
			return ProjectedPageOutcome::refused( $e );
		}

		if ( $outcome === PageRefreshOutcome::Refreshed ) {
			$progress->pageProjected( $pageId );
			return ProjectedPageOutcome::projected();
		}

		$this->logSkippedPage( $pageId, 'it carries no Subject to project' );
		$progress->pageSkipped( $pageId );

		return ProjectedPageOutcome::skipped();
	}

	/**
	 * A skipped page is neither projected nor counted as failed, so without this it would leave no trace
	 * at all — and a page skipped because a replica has not caught up is one the store is left stale on.
	 */
	private function logSkippedPage( int $pageId, string $reason ): void {
		$this->logger->info(
			'NeoWiki graph rebuild skipped page ' . $pageId . ': ' . $reason . '.',
			[ 'pageId' => $pageId ]
		);
	}

	/**
	 * @param int<1, max> $batchSize
	 */
	private function removePageBatch(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		$pageIds = $this->deletedSubjectPageIds->getDeletedSubjectPageIdsAfter( $run->cursor, $batchSize );

		if ( $pageIds === [] ) {
			return $this->recordRun( $run->succeeded() );
		}

		$progress = new RebuildProgress( $run );
		$removed = 0;

		foreach ( $pageIds as $pageId ) {
			if ( $this->removePage( $pageId, $progress, $store, $observer ) ) {
				$removed++;
			}
		}

		$updated = $this->recordRun( $progress->applyTo( $run ) );
		$observer->afterDeletionBatch(
			$updated,
			$removed,
			fn (): int => $this->deletedSubjectPageIds->countDeletedSubjectPages()
		);

		return $updated;
	}

	private function removePage(
		int $pageId,
		RebuildProgress $progress,
		GraphDatabasePlugin $store,
		RebuildBatchObserver $observer
	): bool {
		try {
			$store->deletePage( new PageId( $pageId ) );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logPageFailure( 'remove', $pageId, $e );
			$progress->removalFailed( $pageId );
			$observer->pageFailed( $pageId );
			return false;
		}

		$progress->pageRemoved( $pageId );

		return true;
	}

	/**
	 * A batch's write only lands while the records still have the run going. One ended in the meantime —
	 * cancelled, most of all — keeps the status that ended it, and this batch's work is dropped rather
	 * than written over it: what it projected stays in the store, and resuming re-reads it from the run's
	 * own cursor.
	 */
	private function recordRun( RebuildRun $run ): RebuildRun {
		return $this->runs->updateRunWhileActive( $run ) ?? $run->cancelled();
	}

	private function logPageFailure( string $operation, int $pageId, Exception $e ): void {
		$this->logger->error(
			'NeoWiki graph rebuild failed to ' . $operation . ' page ' . $pageId
			. '. The rebuild continued, so this page is still out of sync in that store. '
			. 'Underlying error: ' . BackendFailureMessage::withoutCredentials( $e->getMessage() ),
			[ 'exception' => $e, 'pageId' => $pageId ]
		);
	}

}
