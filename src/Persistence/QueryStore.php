<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * "Secondary" or "read/query" persistence
 */
interface QueryStore {

	public function savePage( Page $page ): void;

	public function deletePage( PageId $pageId ): void;

}
