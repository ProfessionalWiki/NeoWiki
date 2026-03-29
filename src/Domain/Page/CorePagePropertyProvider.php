<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class CorePagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [
			'name' => $context->pageTitle,
			'creationTime' => $context->creationTime,
			'modificationTime' => $context->modificationTime,
			'categories' => $context->categories,
			'lastEditor' => $context->lastEditor,
		];
	}

}
