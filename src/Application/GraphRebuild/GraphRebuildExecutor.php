<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use Exception;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\DeletedSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIdsLookup;
use Psr\Log\LoggerInterface;
use Throwable;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Walks the wiki for one rebuild run, projecting it into that run's store.
 *
 * A rebuild has two phases. It reprojects every page that carries a Subject, then removes the pages
 * MediaWiki no longer has — re-saving what still exists cannot undo a projection delete that failed, so
 * a page deleted while the store was unreachable would otherwise stay queryable for good. Deleting a
 * page that is already absent is a no-op, so repeating the second phase is safe.
 *
 * The first phase runs in batches, recording progress after each one. That is what makes a rebuild
 * resumable, and it bounds the memory the walk over the wiki needs. The second phase is batched for
 * progress and replication only: the pages MediaWiki no longer has are looked up in one go, since a
 * wiki accumulates far fewer of those than it has pages.
 *
 * Failures are separated by what they say about the rest of the run:
 *
 * - A page that fails to project or to be removed is logged and counted, and the run carries on. One
 *   unreadable page must not cost the wiki its whole rebuild.
 * - A store that will not open — one whose initialize() throws — ends the run before a page is read.
 *   Continuing would only pile up one failure per page in the wiki, and the recorded cursor would then
 *   be worthless for resuming. A store that dies *during* the walk is not recognised as such: its pages
 *   fail one by one, and the run ends as one that reconciled nothing. The exit status still reports it.
 * - A request timeout or a wiki-database error ends the run: the pages after this one would fail
 *   identically.
 */
class GraphRebuildExecutor {

	public function __construct(
		private readonly SubjectPageIdsLookup $subjectPageIds,
		private readonly DeletedSubjectPageIdsLookup $deletedSubjectPageIds,
		private readonly RebuildRunRepository $runs,
		private readonly TitleFactory $titleFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param int<1, max> $batchSize
	 */
	public function execute(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		SubjectPageRebuilder $pageRebuilder,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		$progress = new RebuildProgress( $run );

		try {
			$store->initialize();
			$this->projectPages( $run, $progress, $pageRebuilder, $batchSize, $observer );
			$this->removeDeletedPages( $run, $progress, $store, $batchSize, $observer );
		} catch ( Throwable $e ) {
			// Broader than the Exception boundary elsewhere: a run left recorded as still going blocks
			// every later rebuild of that store, so even a programming bug has to be recorded as its end.
			return $this->finish( $progress->applyTo( $run )->failed( $e->getMessage() ) );
		}

		return $this->finish( $progress->applyTo( $run )->succeeded() );
	}

	private function finish( RebuildRun $run ): RebuildRun {
		$this->runs->updateRun( $run );

		return $run;
	}

	/**
	 * @param int<1, max> $batchSize
	 */
	private function projectPages(
		RebuildRun $run,
		RebuildProgress $progress,
		SubjectPageRebuilder $pageRebuilder,
		int $batchSize,
		RebuildBatchObserver $observer
	): void {
		$totalPages = $this->subjectPageIds->countSubjectPages();

		while ( true ) {
			$pageIds = $this->subjectPageIds->getSubjectPageIdsAfter( $progress->getCursor(), $batchSize );

			if ( $pageIds === [] ) {
				return;
			}

			foreach ( $pageIds as $pageId ) {
				$this->projectPage( $pageId, $progress, $pageRebuilder );
			}

			$observer->afterPageBatch( $this->recordProgress( $run, $progress ), $totalPages );
		}
	}

	private function projectPage( int $pageId, RebuildProgress $progress, SubjectPageRebuilder $pageRebuilder ): void {
		$title = $this->titleFactory->newFromID( $pageId );

		if ( $title === null ) {
			$this->logSkippedPage( $pageId, 'MediaWiki no longer has it' );
			$progress->pageSkipped( $pageId );
			return;
		}

		try {
			$outcome = $pageRebuilder->rebuild( $title );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logPageFailure( 'project', $pageId, $e );
			$progress->pageFailed( $pageId );
			return;
		}

		if ( $outcome === PageRefreshOutcome::Refreshed ) {
			$progress->pageProjected( $pageId );
			return;
		}

		$this->logSkippedPage( $pageId, 'it carries no Subject to project' );
		$progress->pageSkipped( $pageId );
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
	private function removeDeletedPages(
		RebuildRun $run,
		RebuildProgress $progress,
		GraphDatabasePlugin $store,
		int $batchSize,
		RebuildBatchObserver $observer
	): void {
		$pageIds = $this->deletedSubjectPageIds->getDeletedSubjectPageIds();

		if ( $pageIds === [] ) {
			return;
		}

		$removed = 0;

		foreach ( array_chunk( $pageIds, $batchSize ) as $batch ) {
			foreach ( $batch as $pageId ) {
				if ( $this->removePage( $pageId, $progress, $store ) ) {
					$removed++;
				}
			}

			$observer->afterDeletionBatch( $this->recordProgress( $run, $progress ), $removed, count( $pageIds ) );
		}
	}

	private function removePage( int $pageId, RebuildProgress $progress, GraphDatabasePlugin $store ): bool {
		try {
			$store->deletePage( new PageId( $pageId ) );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logPageFailure( 'remove', $pageId, $e );
			$progress->deletionFailed();
			return false;
		}

		return true;
	}

	private function recordProgress( RebuildRun $run, RebuildProgress $progress ): RebuildRun {
		$updated = $progress->applyTo( $run );
		$this->runs->updateRun( $updated );

		return $updated;
	}

	private function logPageFailure( string $operation, int $pageId, Exception $e ): void {
		$this->logger->error(
			'NeoWiki graph rebuild failed to ' . $operation . ' page ' . $pageId
			. '. The rebuild continued, so this page is still out of sync in that store. '
			. 'Underlying error: ' . $e->getMessage(),
			[ 'exception' => $e, 'pageId' => $pageId ]
		);
	}

}
