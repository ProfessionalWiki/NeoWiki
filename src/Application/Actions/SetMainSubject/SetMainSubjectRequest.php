<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject;

class SetMainSubjectRequest {

	public function __construct(
		public int $pageId,
		public ?string $subjectId,
		public ?string $comment = null,
	) {
	}

}
