<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * Where a {@see RebuildRun} stands. A run is Running until it reaches one of the terminal statuses,
 * and only a terminal run can be resumed.
 */
enum RebuildStatus: string {

	case Running = 'running';
	case Succeeded = 'succeeded';
	case Failed = 'failed';
	case Cancelled = 'cancelled';

	public function isTerminal(): bool {
		return $this !== self::Running;
	}

	/**
	 * A run that stopped short of reconciling every page, so resuming it from its cursor has work left
	 * to do. A succeeded run has none.
	 */
	public function isResumable(): bool {
		return $this === self::Failed || $this === self::Cancelled;
	}

}
