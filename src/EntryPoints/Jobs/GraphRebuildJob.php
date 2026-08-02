<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Runs one batch of a background graph rebuild, and queues the next one when the run has work left.
 *
 * The run records are the state, not the queue: this carries only which run to advance, and every
 * execution reads that record before doing anything. A job for a run that has since been cancelled or
 * finished therefore does nothing, which is why cancelling needs no reach into the queue, and why a
 * retried execution is safe — it picks up wherever the run now stands.
 */
class GraphRebuildJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiGraphRebuild';

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Always reports success: a batch that could not run has already ended its run and said why on the
	 * NeoWiki channel, and re-running it would only read a record that now says the run is over.
	 *
	 * A job whose parameters are not what this version files — one queued by another version, or edited
	 * by hand — reads as run 0, which no run is filed under, and is answered the same way as a run that
	 * has since been removed.
	 */
	public function run(): bool {
		NeoWikiExtension::getInstance()
			->newGraphRebuildCoordinator( GraphRebuildCoordinator::BACKGROUND_BATCH_SIZE )
			->continueInBackground( (int)( $this->params['runId'] ?? 0 ) );

		return true;
	}

}
