<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use RuntimeException;

/**
 * A store already has a rebuild queued or going, and two rebuilds of one store would project over each
 * other's work and file progress under two rows.
 */
class RebuildAlreadyRunningException extends RuntimeException {

	public function __construct( public readonly RebuildRun $activeRun ) {
		parent::__construct(
			'A rebuild of graph store "' . $activeRun->store . '" is already ' . $activeRun->status->value
			. ' (run ' . $activeRun->id . '). Wait for it to finish, or cancel it.'
		);
	}

}
