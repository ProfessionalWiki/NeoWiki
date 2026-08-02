<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use JobQueueGroup;
use JobSpecification;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildJobQueue;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\GraphRebuildJob;

/**
 * Files rebuild batches on MediaWiki's job queue.
 *
 * Queued lazily, so a batch filed by a web request reaches the queue after that request's own writes
 * commit. Pushed eagerly it can become visible to a runner before the run row it names does, and a
 * runner that reads no such run drops the batch, leaving the run queued with nothing to carry it on.
 * Under the command line there is no such round, and the push happens there and then.
 *
 * Filed without deduplication: consecutive batches of one run are deliberately identical parameters,
 * and the run's own record is what stops two of them doing the same work.
 */
class MediaWikiRebuildJobQueue implements RebuildJobQueue {

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	public function pushRebuildBatch( int $runId, string $storeName ): void {
		$this->jobQueueGroup->lazyPush(
			new JobSpecification(
				GraphRebuildJob::TYPE,
				[ 'runId' => $runId, 'store' => $storeName ],
				[]
			)
		);
	}

}
