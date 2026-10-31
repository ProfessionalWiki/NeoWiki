<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NeoWiki\Application\PageRenderings;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class SpyPageRenderings implements PageRenderings {

	/**
	 * @var PageId[]
	 */
	public array $expiredPageIds = [];

	/**
	 * @var array<ParserOutput|null>
	 */
	public array $renderingsSeen = [];

	public function expireAfterProjection( PageId $pageId, ?ParserOutput $rendering ): void {
		$this->expiredPageIds[] = $pageId;
		$this->renderingsSeen[] = $rendering;
	}

}
