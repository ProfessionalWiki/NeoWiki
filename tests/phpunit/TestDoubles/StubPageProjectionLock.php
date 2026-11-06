<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Closure;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLock;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLockUnavailableException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Runs each projection at once, or refuses every one, standing for a page another projection holds on to.
 */
class StubPageProjectionLock implements PageProjectionLock {

	private function __construct(
		private readonly bool $available,
	) {
	}

	public static function available(): self {
		return new self( true );
	}

	public static function heldElsewhere(): self {
		return new self( false );
	}

	public function whileHeld( PageId $pageId, Closure $projection ): mixed {
		if ( !$this->available ) {
			throw new PageProjectionLockUnavailableException( $pageId );
		}

		return $projection();
	}

}
