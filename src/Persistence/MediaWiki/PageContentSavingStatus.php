<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

class PageContentSavingStatus {

	public const REVISION_CREATED = 'revisionCreated';
	public const NO_CHANGES = 'noChanges';
	public const ERROR = 'error';

	public function __construct(
		public readonly string $status,
		public readonly ?string $errorMessage = null,
	) {
	}

}
