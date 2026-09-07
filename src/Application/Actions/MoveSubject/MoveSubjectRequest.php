<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject;

readonly class MoveSubjectRequest {

	public function __construct(
		public string $subjectId,
		public int $targetPageId,
		public bool $makeMainSubject = false,
		public ?string $comment = null,
	) {
	}

}
