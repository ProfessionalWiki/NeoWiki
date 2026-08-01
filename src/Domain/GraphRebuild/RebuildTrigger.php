<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * What started a {@see RebuildRun}, so an operator reading the run records can tell an unattended
 * rebuild from one they started themselves.
 */
enum RebuildTrigger: string {

	case Cli = 'cli';

}
