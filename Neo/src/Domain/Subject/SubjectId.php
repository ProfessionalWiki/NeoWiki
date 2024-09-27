<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use Stringable;

readonly class SubjectId implements Stringable {

	public string $text;

	public function __construct( string $text ) {
		if ( !self::isValid( $text ) ) {
			throw new \InvalidArgumentException( "Subject ID has the wrong format: '$text'" );
		}

		$this->text = $text;
	}

	public function equals( self $other ): bool {
		return $this->text === $other->text;
	}

	public static function createNew( IdGenerator $idGenerator ): self {
		return new self( 's' . $idGenerator->generate() );
	}

	public static function isValid( string $text ): bool {
		return preg_match( '/^s[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}$/', $text ) === 1;
	}

	public function __toString(): string {
		return $this->text;
	}

}
