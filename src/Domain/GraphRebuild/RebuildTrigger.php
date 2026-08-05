<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * What started a {@see RebuildRun}, so an operator reading the run records can tell an unattended
 * rebuild from one they started themselves, and can tell which surface started it.
 */
enum RebuildTrigger: string {

	case Cli = 'cli';
	case Api = 'api';
	case Ui = 'ui';

	/**
	 * A Mapping page changed and the store holding that projection was rebuilt without anyone asking.
	 */
	case Auto = 'auto';

}
