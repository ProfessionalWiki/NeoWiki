<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class DateProperty extends PropertyDefinition {

	/**
	 * Matches xsd:date-like strings: a calendar date with no time or timezone
	 * component. Mirrors the regex used in the TypeScript DateType; a
	 * subsequent calendar-overflow check rejects inputs like `2025-02-30`.
	 */
	private const ISO_DATE_REGEX =
		'/^(-?\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/';

	public function __construct(
		PropertyCore $core,
		private readonly ?string $minimum,
		private readonly ?string $maximum,
	) {
		self::ensureValidBoundOrNull( 'minimum', $minimum );
		self::ensureValidBoundOrNull( 'maximum', $maximum );

		if ( is_string( $core->default ) ) {
			self::ensureValidBoundOrNull( 'default', $core->default );
		}

		parent::__construct( $core );
	}

	private static function ensureValidBoundOrNull( string $field, ?string $value ): void {
		if ( $value === null ) {
			return;
		}

		if ( !self::isValidIsoDate( $value ) ) {
			throw new InvalidArgumentException(
				"DateProperty {$field} must be a strict ISO 8601 date (YYYY-MM-DD), got '{$value}'"
			);
		}
	}

	private static function isValidIsoDate( string $value ): bool {
		if ( preg_match( self::ISO_DATE_REGEX, $value, $matches ) !== 1 ) {
			return false;
		}

		// Reject calendar overflows that the regex alone cannot detect (e.g. Feb 30).
		return checkdate( (int)$matches[2], (int)$matches[3], (int)$matches[1] );
	}

	/**
	 * Parses a strict ISO 8601 calendar date (`YYYY-MM-DD`). Returns a
	 * DateTimeImmutable at UTC midnight, or null if the value is malformed,
	 * carries a time/timezone component, or is a calendar overflow.
	 *
	 * Mirrors the TS parseStrictDate helper.
	 */
	public static function parseStrictDate( string $value ): ?DateTimeImmutable {
		if ( !self::isValidIsoDate( $value ) ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value . 'T00:00:00Z' );
		} catch ( Exception ) {
			return null;
		}
	}

	public function getPropertyType(): string {
		return DateType::NAME;
	}

	public function getMinimum(): ?string {
		return $this->minimum;
	}

	public function hasMinimum(): bool {
		return $this->minimum !== null;
	}

	public function getMaximum(): ?string {
		return $this->maximum;
	}

	public function hasMaximum(): bool {
		return $this->maximum !== null;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			minimum: $property['minimum'] ?? null,
			maximum: $property['maximum'] ?? null,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'minimum' => $this->getMinimum(),
			'maximum' => $this->getMaximum(),
		];
	}

}
