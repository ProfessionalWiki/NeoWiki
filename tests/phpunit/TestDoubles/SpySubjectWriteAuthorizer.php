<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Authorizes or denies every write, recording the PageIds it was asked about.
 *
 * $deniedPageIds refuses named pages while $allowed still governs the rest, so a write touching two
 * pages can be denied on one of them.
 */
class SpySubjectWriteAuthorizer implements SubjectWriteAuthorizer {

	public ?PageId $authorizedPageId = null;

	/**
	 * Every PageId passed to authorize(), in call order. The count matters as well as the values:
	 * each call charges the edit rate limit in production.
	 *
	 * @var PageId[]
	 */
	public array $authorizedPageIds = [];

	/**
	 * @param int[] $deniedPageIds
	 */
	public function __construct(
		private bool $allowed,
		private array $deniedPageIds = []
	) {
	}

	public function authorize( PageId $pageId ): bool {
		$this->authorizedPageId = $pageId;
		$this->authorizedPageIds[] = $pageId;

		if ( in_array( $pageId->id, $this->deniedPageIds, true ) ) {
			return false;
		}

		return $this->allowed;
	}

}
