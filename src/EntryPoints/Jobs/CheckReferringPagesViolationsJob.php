<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Checks again the pages whose Subjects relate to given Subjects, one batch per job: each job queues the
 * next with the page to continue after, so a Subject many others relate to never holds one job runner for
 * long.
 */
class CheckReferringPagesViolationsJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiCheckReferringPagesViolations';

	public const string ALL_REFERRING_PAGES = 'all';
	public const string PAGES_NOT_FINDING_THEM = 'not-finding';

	private const int BATCH_SIZE = 100;

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * @param string[] $targetIds
	 * @param string $pages self::ALL_REFERRING_PAGES or self::PAGES_NOT_FINDING_THEM
	 */
	public static function newSpecification( array $targetIds, string $pages ): JobSpecification {
		return self::newBatchSpecification( [ 'targetIds' => array_values( $targetIds ), 'pages' => $pages ] );
	}

	private static function newBatchSpecification( array $params ): JobSpecification {
		return new JobSpecification( self::TYPE, $params, [ 'removeDuplicates' => true ] );
	}

	public function run(): bool {
		$targetIds = array_map( 'strval', (array)( $this->params['targetIds'] ?? [] ) );

		if ( $targetIds === [] ) {
			return true;
		}

		$extension = NeoWikiExtension::getInstance();
		$checker = ( $this->params['pages'] ?? null ) === self::PAGES_NOT_FINDING_THEM
			? $extension->newNotFoundTargetViolationChecker()
			: $extension->newReferringPagesViolationChecker();

		$next = $checker->checkBatch(
			$targetIds,
			(int)( $this->params['afterPageId'] ?? 0 ),
			(int)( $this->params['batchSize'] ?? self::BATCH_SIZE )
		);

		if ( $next !== null ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
				self::newBatchSpecification( [ 'afterPageId' => $next ] + $this->params )
			);
		}

		return true;
	}

}
