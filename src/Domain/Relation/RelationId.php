<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use Ramsey\Uuid\Uuid;

readonly class RelationId {

	private string $text;

	public function __construct( string $text ) {
		if ( !self::isValid( $text ) ) {
			throw new \InvalidArgumentException( "Relation ID has the wrong format: '$text'" );
		}

		$this->text = $text;
	}

	public function equals( self $other ): bool {
		return $this->text === $other->text;
	}

	public static function createNew( IdGenerator $idGenerator ): self {
		return new self( 'r' . $idGenerator->generate() );
	}

	public static function isValid( string $text ): bool {
		return preg_match( '/^r[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}$/', $text ) === 1;
	}

	public function asString(): string {
		return $this->text;
	}

}
