<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * Where a {@see RebuildRun} stands. A run is Queued or Running until it reaches one of the terminal
 * statuses, and only a terminal run can be resumed.
 */
enum RebuildStatus: string {

	case Queued = 'queued';
	case Running = 'running';
	case Succeeded = 'succeeded';
	case Failed = 'failed';
	case Cancelled = 'cancelled';

	public function isTerminal(): bool {
		return !$this->isActive();
	}

	/**
	 * A run that still has work ahead of it, whether or not it has begun. A store may have only one, so
	 * this is what a second rebuild of it is refused against.
	 */
	public function isActive(): bool {
		return $this === self::Queued || $this === self::Running;
	}

	/**
	 * A run that stopped short of reconciling every page, so resuming it from its cursor has work left
	 * to do. A succeeded run has none.
	 */
	public function isResumable(): bool {
		return $this === self::Failed || $this === self::Cancelled;
	}

}
