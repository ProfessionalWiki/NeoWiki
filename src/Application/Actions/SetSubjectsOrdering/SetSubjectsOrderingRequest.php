<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

readonly class SetSubjectsOrderingRequest {

	/**
	 * @param string[] $childSubjectIds
	 */
	public function __construct(
		public int $pageId,
		public ?string $mainSubjectId,
		public array $childSubjectIds,
		public ?string $comment = null,
	) {
	}

}
