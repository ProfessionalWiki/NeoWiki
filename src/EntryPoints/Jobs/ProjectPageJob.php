<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use JobSpecification;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Projects a page as it stands when the job runs, for a projection that failed after the operation that changed
 * the page had committed, or one that must not leave a failing graph store behind. A failure fails the job, and
 * the queue runs it again; unless the job is filed not to isolate failures, a failing graph store or Page
 * Property Provider is logged and skipped instead.
 */
class ProjectPageJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiProjectPage';

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Filed as deduplicable: a second copy queued while the first is still waiting has nothing to add.
	 */
	public static function newSpecification( PageId $pageId, bool $isolateFailures ): JobSpecification {
		return new JobSpecification(
			self::TYPE,
			[ 'pageId' => $pageId->id, 'isolateFailures' => $isolateFailures ],
			[ 'removeDuplicates' => true ]
		);
	}

	/**
	 * A job whose parameters are not what this version files names no page, and projects nothing rather
	 * than guessing at one.
	 */
	public function run(): bool {
		$pageId = (int)( $this->params['pageId'] ?? 0 );

		if ( $pageId > 0 ) {
			NeoWikiExtension::getInstance()
				->newCurrentPageProjector( isolateFailures: $this->params['isolateFailures'] ?? true )
				->project( new PageId( $pageId ), null );
		}

		return true;
	}

}
