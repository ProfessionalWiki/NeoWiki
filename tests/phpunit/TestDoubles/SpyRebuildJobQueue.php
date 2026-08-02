<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildJobQueue;
use RuntimeException;
use Throwable;

/**
 * Records the batches a rebuild filed instead of queueing them, so a test can drive them itself — or,
 * told to, stands in for a queue that will not take them.
 */
class SpyRebuildJobQueue implements RebuildJobQueue {

	public const string FAILURE_MESSAGE = 'the job queue is unreachable';

	/**
	 * @var int[] The ids of the runs the batches were filed for.
	 */
	public array $pushedBatches = [];

	public function __construct(
		private readonly ?Throwable $failure = null,
	) {
	}

	public static function refusingEverything(): self {
		return new self( new RuntimeException( self::FAILURE_MESSAGE ) );
	}

	public function pushRebuildBatch( int $runId ): void {
		if ( $this->failure !== null ) {
			throw $this->failure;
		}

		$this->pushedBatches[] = $runId;
	}

}
