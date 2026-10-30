<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

interface PageRenderings {

	/**
	 * Expires the cached renderings of a page just projected that can show what the graph stores held before:
	 * when the rendering MediaWiki made before the projection is given, only if it read the graph stores.
	 */
	public function expireAfterProjection( PageId $pageId, ?ParserOutput $rendering ): void;

}
