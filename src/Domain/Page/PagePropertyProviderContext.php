<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class PagePropertyProviderContext {

	/**
	 * @param string $pageTitle Full prefixed page title, e.g. "Help:My Page" ("My Page" in the main namespace)
	 * @param int $namespaceId MediaWiki namespace ID, e.g. 0 for the main namespace or 12 for Help
	 * @param string $creationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string $modificationTime In the standard MediaWiki format, ie 20230726163439
	 * @param string[] $categories
	 * @param string $lastEditor Plain username of the last editor, e.g. "JohnDoe". Empty string if unknown.
	 */
	public function __construct(
		public PageId $pageId,
		public string $pageTitle,
		public int $namespaceId,
		public string $creationTime,
		public string $modificationTime,
		public array $categories,
		public string $lastEditor,
	) {
	}

}
