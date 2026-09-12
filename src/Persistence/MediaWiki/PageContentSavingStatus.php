<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class PageContentSavingStatus {

	public const string REVISION_CREATED = 'revisionCreated';
	public const string NO_CHANGES = 'noChanges';
	public const string ERROR = 'error';

	public function __construct(
		public readonly string $status,
		public readonly ?string $errorMessage = null,
		/**
		 * The page written, known whenever a revision was created. Saves that name their page by id
		 * already have it; a page created by title learns it here rather than reading it back.
		 */
		public readonly ?PageId $pageId = null,
	) {
	}

}
