<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use Stringable;

readonly class SubjectId implements Stringable {

	private const string BARE_PATTERN = '/^s[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}\z/';
	private const string QUALIFIED_PATTERN = '/^([A-Za-z0-9+_-]+):([A-Za-z0-9._~!$&\'()*+,;=:@-]+)\z/';

	public string $text;
	private ?string $source;
	private string $localId;

	public function __construct( string $text ) {
		if ( preg_match( self::BARE_PATTERN, $text ) === 1 ) {
			$this->source = null;
			$this->localId = $text;
		} elseif ( preg_match( self::QUALIFIED_PATTERN, $text, $matches ) === 1 ) {
			$this->source = $matches[1];
			$this->localId = $matches[2];
		} else {
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
		return preg_match( self::BARE_PATTERN, $text ) === 1
			|| preg_match( self::QUALIFIED_PATTERN, $text ) === 1;
	}

	/**
	 * The source key, or null for a local Subject (bare id form).
	 */
	public function getSource(): ?string {
		return $this->source;
	}

	public function getLocalId(): string {
		return $this->localId;
	}

	public function __toString(): string {
		return $this->text;
	}

}
