<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\EditNotice;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

readonly class SubjectEditNoticeContext {

	/**
	 * @param string $pageDbKey Unprefixed database key of the page being edited, e.g. "New_York" for "Help:New York"
	 * @param int $namespaceId MediaWiki namespace ID, e.g. 0 for the main namespace or 12 for Help
	 * @param string|null $schemaName Schema of the Subject being edited, absent when no Subject is in play yet
	 * @param bool $namespaceHasSubpages Whether the namespace treats slashes as subpage separators
	 */
	public function __construct(
		public PageId $pageId,
		public string $pageDbKey,
		public int $namespaceId,
		public ?string $schemaName = null,
		public bool $namespaceHasSubpages = false,
	) {
	}

}
