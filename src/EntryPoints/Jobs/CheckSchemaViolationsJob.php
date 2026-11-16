<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Checks again the pages publishing Subjects of a Schema, one batch per job: each job queues the next with
 * the Subject to continue after, so a Schema with many Subjects never holds one job runner for long.
 */
class CheckSchemaViolationsJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiCheckSchemaViolations';

	private const int BATCH_SIZE = 100;

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	public static function newSpecification( string $schemaName ): JobSpecification {
		return self::newBatchSpecification( [ 'schemaName' => $schemaName ] );
	}

	private static function newBatchSpecification( array $params ): JobSpecification {
		return new JobSpecification( self::TYPE, $params, [ 'removeDuplicates' => true ] );
	}

	public function run(): bool {
		$schemaName = (string)( $this->params['schemaName'] ?? '' );

		if ( $schemaName === '' ) {
			return true;
		}

		$next = NeoWikiExtension::getInstance()->newSchemaViolationChecker()->checkBatch(
			$schemaName,
			(string)( $this->params['afterSubjectId'] ?? '' ),
			(int)( $this->params['afterPageId'] ?? 0 ),
			(int)( $this->params['batchSize'] ?? self::BATCH_SIZE )
		);

		if ( $next !== null ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
				self::newBatchSpecification(
					[ 'afterSubjectId' => $next['subjectId'], 'afterPageId' => $next['pageId'] ] + $this->params
				)
			);
		}

		return true;
	}

}
