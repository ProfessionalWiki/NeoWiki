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
	 * @param string $content Serialized main slot content of the revision, e.g. the wikitext.
	 *   Empty string if the content is unavailable.
	 * @param string $contentModel Content model of the main slot, e.g. "wikitext".
	 *   Empty string if the content is unavailable.
	 * @param array<string, int|float|string|bool|null> $parserProperties Properties recorded during parsing of the
	 *   content (MediaWiki page properties, e.g. "defaultsort" or values set by parser hooks via
	 *   ParserOutput::setPageProperty). These are inputs from the parse, not to be confused with the NeoWiki
	 *   Page Properties that providers return.
	 */
	public function __construct(
		public PageId $pageId,
		public string $pageTitle,
		public int $namespaceId,
		public string $creationTime,
		public string $modificationTime,
		public array $categories,
		public string $lastEditor,
		public string $content,
		public string $contentModel,
		public array $parserProperties,
	) {
	}

}
