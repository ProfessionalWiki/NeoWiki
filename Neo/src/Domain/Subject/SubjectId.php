<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class SubjectId {

	public function __construct(
		public readonly string $text,
	) {
		// TODO: validation
	}

	public function equals( self $other ): bool {
		return $this->text === $other->text;
	}

	public static function createNew( GuidGenerator $guidGenerator ): self {
		return new self( $guidGenerator->generate() );
	}

}
