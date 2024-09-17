<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki;

class PageContentSavingStatus {

	public const string REVISION_CREATED = 'revisionCreated';
	public const string NO_CHANGES = 'noChanges';
	public const string ERROR = 'error';

	public function __construct(
		public readonly string $status,
		public readonly ?string $errorMessage = null,
	) {
	}

}
