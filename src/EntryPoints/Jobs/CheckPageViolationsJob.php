<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use JobSpecification;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Checks the Subjects a page publishes against their Schemas and records their Violations. Nothing is
 * caught: a failure fails the job and the queue runs it again, which is safe as a check replaces what the
 * page had.
 */
class CheckPageViolationsJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiCheckPageViolations';

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Filed as deduplicable: the job reads the page when it runs, so a second copy queued while the first
	 * is still waiting has nothing to add.
	 */
	public static function newSpecification( PageId $pageId ): JobSpecification {
		return new JobSpecification(
			self::TYPE,
			[ 'pageId' => $pageId->id ],
			[ 'removeDuplicates' => true ]
		);
	}

	public function run(): bool {
		$pageId = (int)( $this->params['pageId'] ?? 0 );

		if ( $pageId > 0 ) {
			NeoWikiExtension::getInstance()->newPageViolationChecker()->checkPage( new PageId( $pageId ) );
		}

		return true;
	}

}
