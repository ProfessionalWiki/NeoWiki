<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use RuntimeException;

/**
 * Another process held the store's start lock for longer than we waited for it. Almost always a rebuild
 * of that store being started at the same moment, which is exactly what the lock exists to refuse.
 */
class RebuildStartLockUnavailableException extends RuntimeException {

	public function __construct( string $store ) {
		parent::__construct(
			'A rebuild of graph store "' . $store . '" is being started by something else. Try again.'
		);
	}

}
