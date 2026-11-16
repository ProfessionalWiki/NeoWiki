<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndexRebuilder;

/**
 * Checks every page for Violations, one batch per job: each job queues the next with the page to continue
 * after, so checking a large wiki never holds one job runner for long.
 */
class RebuildViolationIndexJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiRebuildViolationIndex';

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	public static function newSpecification(): JobSpecification {
		return self::newBatchSpecification( [] );
	}

	private static function newBatchSpecification( array $params ): JobSpecification {
		return new JobSpecification( self::TYPE, $params, [ 'removeDuplicates' => true ] );
	}

	public function run(): bool {
		$batch = $this->newRebuilder()->checkBatch(
			(int)( $this->params['walk'] ?? 0 ),
			(int)( $this->params['afterPageId'] ?? 0 )
		);

		if ( $batch['next'] !== null ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
				self::newBatchSpecification( $batch['next'] + $this->params )
			);
		}

		return true;
	}

	private function newRebuilder(): DatabaseViolationIndexRebuilder {
		return new DatabaseViolationIndexRebuilder(
			db: MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase(),
			checker: NeoWikiExtension::getInstance()->newPageViolationChecker(),
			batchSize: (int)( $this->params['batchSize'] ?? DatabaseViolationIndexRebuilder::DEFAULT_BATCH_SIZE ),
		);
	}

}
