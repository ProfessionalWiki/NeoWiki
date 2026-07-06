<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

interface GraphDatabasePlugin {

	public function savePage( Page $page ): void;

	public function deletePage( PageId $pageId ): void;

}
