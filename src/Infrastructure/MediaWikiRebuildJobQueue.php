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
 * Consecutive batches of one run are deliberately identical, so filing them as deduplicable does not
 * collapse the chain: the queue only ever drops a batch that matches one still waiting to be claimed, and
 * a batch pushing its successor was claimed to do so. What it does drop is the second copy of a batch
 * that got run twice — a retry, or two runners racing the same job — which is where a run would otherwise
 * fork into two chains advancing one cursor. Best-effort rather than a guarantee, which is all the run
 * records need it to be: they already stop a duplicate from doing the same work twice.
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
				[ 'removeDuplicates' => true ]
			)
		);
	}

}
