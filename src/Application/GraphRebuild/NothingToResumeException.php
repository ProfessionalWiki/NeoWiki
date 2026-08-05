<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use RuntimeException;

/**
 * There is no unfinished rebuild of this store to pick back up: either it has never been rebuilt, or
 * its last rebuild finished.
 */
class NothingToResumeException extends RuntimeException {

	/**
	 * @param RebuildRun|null $latestRun The store's last rebuild, or null when it has never had one.
	 *        Nothing to resume does not mean the store is reconciled — one never rebuilt holds none of
	 *        the wiki, and one whose last run finished may still have left pages behind — so a caller
	 *        resuming every store reads this to tell those apart.
	 */
	public function __construct( string $store, public readonly ?RebuildRun $latestRun ) {
		parent::__construct(
			'Graph store "' . $store . '" has no unfinished rebuild to resume. Start a new one instead.'
		);
	}

}
