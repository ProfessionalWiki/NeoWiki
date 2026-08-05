<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * Which half of a rebuild a run is in: reprojecting the pages the wiki has, or removing the ones it no
 * longer has. A run walks the first, then the second.
 *
 * The phase is recorded because each walks its own set of page ids, so the run's cursor means nothing
 * without it: page 40 in the projection walk and page 40 in the removal walk are different positions.
 */
enum RebuildPhase: string {

	case Pages = 'pages';
	case Deletions = 'deletions';

}
