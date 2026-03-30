<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\PageDateTime;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;

class CorePagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [
			'name' => $context->pageTitle,
			'creationTime' => new PageDateTime( $context->creationTime ),
			'modificationTime' => new PageDateTime( $context->modificationTime ),
			'categories' => $context->categories,
			'lastEditor' => $context->lastEditor,
		];
	}

}
