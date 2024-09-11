<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

readonly class CreateSubjectsRequest {

	public function __construct(
		public PageId $pageId,
		public string $subjectsJson
	) {
	}

}
