<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageDependencyRecorder;

class SpyPageDependencyRecorder implements PageDependencyRecorder {

	/**
	 * @var Title[]
	 */
	public array $recordedPages = [];

	public function recordDependencyOn( Title $page ): void {
		$this->recordedPages[] = $page;
	}

}
