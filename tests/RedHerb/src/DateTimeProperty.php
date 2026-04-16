<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class DateTimeProperty extends PropertyDefinition {

	/**
	 * Matches xsd:dateTime-like strings with an explicit timezone offset or `Z`.
	 * Mirrors the regex used in the TypeScript DateTimeType; a subsequent
	 * calendar-overflow check rejects inputs like `2025-02-30T00:00:00Z`.
	 */
	private const ISO_DATE_TIME_REGEX =
		'/^(-?\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,9})?(?<offset>Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/';

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

		if ( !self::isValidIsoDateTime( $value ) ) {
			throw new InvalidArgumentException(
				"DateTimeProperty {$field} must be a strict ISO 8601 datetime with an explicit timezone, got '{$value}'"
			);
		}
	}

	private static function isValidIsoDateTime( string $value ): bool {
		if ( preg_match( self::ISO_DATE_TIME_REGEX, $value, $matches ) !== 1 ) {
			return false;
		}

		// Reject calendar overflows that the regex alone cannot detect (e.g. Feb 30).
		return checkdate( (int)$matches[2], (int)$matches[3], (int)$matches[1] );
	}

	public function getPropertyType(): string {
		return DateTimeType::NAME;
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
