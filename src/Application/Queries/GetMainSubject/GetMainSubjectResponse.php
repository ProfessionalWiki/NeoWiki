<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetMainSubject;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

readonly class GetMainSubjectResponse {

	public function __construct(
		public int $pageId,
		public ?GetSubjectResponseItem $subject,
	) {
	}

}
