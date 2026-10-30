<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Keeps the graph projections of one page from overlapping, so each reads the page only once the one
 * before it has written, and the graph stores end up holding the page as it last stood. A projection
 * reads the page as committed when it first reads under the lock, which a caller that read the page
 * earlier in the same transaction would not see.
 */
interface PageProjectionLock {

	/**
	 * @template T
	 * @param Closure(): T $projection
	 * @return T
	 *
	 * @throws PageProjectionLockUnavailableException When another projection of the page held the lock for
	 *         longer than this waits.
	 */
	public function whileHeld( PageId $pageId, Closure $projection ): mixed;

}
