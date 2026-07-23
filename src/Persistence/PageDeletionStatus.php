<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

class PageDeletionStatus {

	public function __construct(
		public readonly bool $succeeded,
		public readonly ?string $errorMessage = null,
	) {
	}

}
