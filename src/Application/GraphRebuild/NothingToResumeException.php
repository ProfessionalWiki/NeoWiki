<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use RuntimeException;

/**
 * There is no unfinished rebuild of this store to pick back up: either it has never been rebuilt, or
 * its last rebuild finished.
 */
class NothingToResumeException extends RuntimeException {

	public function __construct( public readonly string $store ) {
		parent::__construct(
			'Graph store "' . $store . '" has no unfinished rebuild to resume. Start a new one instead.'
		);
	}

}
