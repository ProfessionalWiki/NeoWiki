<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * Takes note of the pages a result depends on, so that it can be rebuilt when one of them changes.
 */
interface PageDependencyRecorder {

	public function recordDependencyOn( Title $page ): void;

	/**
	 * The page of a Schema of this wiki. A Schema from another Source has no page here.
	 */
	public function recordDependencyOnSchema( SchemaReference $schema ): void;

}
