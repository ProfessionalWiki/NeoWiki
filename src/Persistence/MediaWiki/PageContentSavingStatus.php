<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class PageContentSavingStatus {

	public const string REVISION_CREATED = 'revisionCreated';
	public const string NO_CHANGES = 'noChanges';
	/**
	 * The page has a revision newer than the one the write was based on, so nothing was written.
	 */
	public const string CONFLICT = 'conflict';
	public const string ERROR = 'error';

	public function __construct(
		public readonly string $status,
		public readonly ?string $errorMessage = null,
		/**
		 * The page written, known whenever a revision was created. Saves that name their page by id
		 * already have it; a page created by title learns it here rather than reading it back.
		 */
		public readonly ?PageId $pageId = null,
		/**
		 * The revision the write created; null when it created none.
		 */
		public readonly ?int $revisionId = null,
	) {
	}

}
