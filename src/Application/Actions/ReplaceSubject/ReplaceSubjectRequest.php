<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class ReplaceSubjectRequest {

	/**
	 * @param array<string, mixed> $statements
	 */
	public function __construct(
		public SubjectId $subjectId,
		public int $baseRevisionId,
		public ?string $label,
		public array $statements,
		public ?string $comment,
		public array $dependentSubjects,
	) {
	}

}
