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
 * Pushed without deduplication: consecutive batches of one run are deliberately identical parameters,
 * and the run's own record is what stops two of them doing the same work.
 */
class MediaWikiRebuildJobQueue implements RebuildJobQueue {

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	public function pushRebuildBatch( int $runId, string $storeName ): void {
		$this->jobQueueGroup->push(
			new JobSpecification(
				GraphRebuildJob::TYPE,
				[ 'runId' => $runId, 'store' => $storeName ],
				[]
			)
		);
	}

}
