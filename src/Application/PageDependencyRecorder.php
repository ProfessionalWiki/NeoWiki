<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Title\Title;

/**
 * Takes note of the pages a result was built from, so that it can be rebuilt when one of them changes.
 */
interface PageDependencyRecorder {

	public function recordDependencyOn( Title $page ): void;

}
