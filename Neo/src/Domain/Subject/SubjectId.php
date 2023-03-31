<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

class SubjectId {

	public function __construct(
		public readonly string $text,
	) {
	}

}
