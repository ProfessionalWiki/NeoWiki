<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageDependencyRecorder;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

class NullPageDependencyRecorder implements PageDependencyRecorder {

	public function recordDependencyOn( Title $page ): void {
	}

	public function recordDependencyOnSchema( SchemaReference $schema ): void {
	}

}
