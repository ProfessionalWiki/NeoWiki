<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use RuntimeException;

/**
 * This store has no rebuild queued or going, so there is nothing to call off.
 */
class NothingToCancelException extends RuntimeException {

	public function __construct( string $store ) {
		parent::__construct( 'Graph store "' . $store . '" has no rebuild to cancel.' );
	}

}
