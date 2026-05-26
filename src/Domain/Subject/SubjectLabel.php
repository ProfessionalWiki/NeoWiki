<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

readonly class SubjectLabel {

	public function __construct(
		public string $text,
	) {
	}

}
