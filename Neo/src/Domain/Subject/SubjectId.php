<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use Ramsey\Uuid\Uuid;

class SubjectId {

	public readonly string $text;

	public function __construct( string $text ) {
		if ( !Uuid::isValid( $text ) ) {
			throw new \InvalidArgumentException( 'Subject ID has the wrong format' );
		}

		$this->text = $text;
	}

	public function equals( self $other ): bool {
		return $this->text === $other->text;
	}

	public static function createNew( GuidGenerator $guidGenerator ): self {
		return new self( $guidGenerator->generate() );
	}

}
