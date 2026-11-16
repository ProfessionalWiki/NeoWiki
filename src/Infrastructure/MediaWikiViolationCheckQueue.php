<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use JobQueueGroup;
use ProfessionalWiki\NeoWiki\Application\Validation\ViolationCheckQueue;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckPageViolationsJob;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckReferringPagesViolationsJob;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckSchemaViolationsJob;

/**
 * Queued lazily, so a check filed by a web request reaches the queue after that request's own writes
 * commit and reads the revision they made.
 */
class MediaWikiViolationCheckQueue implements ViolationCheckQueue {

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	public function queuePageCheck( PageId $pageId ): void {
		$this->jobQueueGroup->lazyPush( CheckPageViolationsJob::newSpecification( $pageId ) );
	}

	public function queueSchemaCheck( string $schemaName ): void {
		$this->jobQueueGroup->lazyPush( CheckSchemaViolationsJob::newSpecification( $schemaName ) );
	}

	public function queueReferringPagesCheck( string ...$targetIds ): void {
		$this->jobQueueGroup->lazyPush(
			CheckReferringPagesViolationsJob::newSpecification( $targetIds, CheckReferringPagesViolationsJob::ALL_REFERRING_PAGES )
		);
	}

	public function queueNotFoundTargetCheck( string ...$targetIds ): void {
		$this->jobQueueGroup->lazyPush(
			CheckReferringPagesViolationsJob::newSpecification( $targetIds, CheckReferringPagesViolationsJob::PAGES_NOT_FINDING_THEM )
		);
	}

}
