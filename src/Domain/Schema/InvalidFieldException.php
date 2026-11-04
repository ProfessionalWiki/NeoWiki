<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

/**
 * A field of a Property Definition holds a value its Property Type cannot use.
 */
class InvalidFieldException extends InvalidArgumentException {

	/**
	 * @param string $reason In English, for the person who wrote the field, e.g. `must be a number, not "five"`.
	 */
	public function __construct(
		public readonly string $field,
		public readonly string $reason,
	) {
		parent::__construct( $field . ' ' . $reason );
	}

	public static function mustBe( string $field, string $expected, mixed $value ): self {
		return new self( $field, 'must be ' . $expected . ', not ' . self::asJson( $value ) );
	}

	public static function asJson( mixed $value ): string {
		return (string)json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

}
