<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class PagePropertyProviderContext {

	/**
	 * @param string $creationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string $modificationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string[] $categories
	 */
	public function __construct(
		public PageId $pageId,
		public string $title,
		public string $creationTime,
		public string $modificationTime,
		public array $categories,
		public string $lastEditor,
	) {
	}

}
