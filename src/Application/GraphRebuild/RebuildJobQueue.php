<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

/**
 * Where a rebuild's next batch is filed to be run out of band.
 *
 * One batch per queued item rather than one whole rebuild, so a rebuild of a large wiki is many short
 * pieces of work instead of one that outlives whatever is running it.
 */
interface RebuildJobQueue {

	public function pushRebuildBatch( int $runId, string $storeName ): void;

}
