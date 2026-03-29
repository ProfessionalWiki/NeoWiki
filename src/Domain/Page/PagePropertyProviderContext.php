<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class PagePropertyProviderContext {

	/**
	 * @param string $pageTitle Page title text without namespace prefix, e.g. "My Page" not "Talk:My Page"
	 * @param string $creationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string $modificationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string[] $categories
	 * @param string $lastEditor Plain username of the last editor, e.g. "JohnDoe". Empty string if unknown.
	 */
	public function __construct(
		public PageId $pageId,
		public string $pageTitle,
		public string $creationTime,
		public string $modificationTime,
		public array $categories,
		public string $lastEditor,
	) {
	}

}
