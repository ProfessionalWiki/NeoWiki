<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class DateProperty extends PropertyDefinition {

	private const string INVALID_MIN_PRECISION = 'DateProperty minPrecision must be "month" or "day"';

	public function __construct(
		PropertyCore $core,
		private readonly ?string $minimum,
		private readonly ?string $maximum,
		private readonly ?DatePrecision $minPrecision,
	) {
		self::ensureValidDateOrNull( 'minimum', $minimum );
		self::ensureValidDateOrNull( 'maximum', $maximum );

		if ( is_string( $core->default ) ) {
			self::ensureValidDateOrNull( 'default', $core->default );
		}

		if ( $minPrecision === DatePrecision::Year ) {
			throw new InvalidArgumentException( self::INVALID_MIN_PRECISION );
		}

		parent::__construct( $core );
	}

	private static function ensureValidDateOrNull( string $field, ?string $value ): void {
		if ( $value === null ) {
			return;
		}

		if ( PartialDate::tryParse( $value ) === null ) {
			throw new InvalidArgumentException(
				"DateProperty {$field} must be an ISO 8601 date (YYYY, YYYY-MM or YYYY-MM-DD), got '{$value}'"
			);
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

	public function getMinPrecision(): ?DatePrecision {
		return $this->minPrecision;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			minimum: $property['minimum'] ?? null,
			maximum: $property['maximum'] ?? null,
			minPrecision: self::minPrecisionFromJson( $property['minPrecision'] ?? null ),
		);
	}

	private static function minPrecisionFromJson( mixed $minPrecision ): ?DatePrecision {
		if ( $minPrecision === null ) {
			return null;
		}

		$precision = is_string( $minPrecision ) ? DatePrecision::tryFrom( $minPrecision ) : null;

		if ( $precision === null ) {
			throw new InvalidArgumentException( self::INVALID_MIN_PRECISION );
		}

		return $precision;
	}

	public function nonCoreToJson(): array {
		return [
			'minimum' => $this->getMinimum(),
			'maximum' => $this->getMaximum(),
			'minPrecision' => $this->minPrecision?->value,
		];
	}

	/**
	 * A full date must also be a calendar date, which only `format` can say; the shorter forms
	 * have nothing for it to check, so they pass on their length alone.
	 */
	public function toJsonSchema(): array {
		return $this->listValueSchema( [
			'type' => 'string',
			'pattern' => PartialDate::patternOfAtLeast( $this->minPrecision ?? DatePrecision::Year ),
			'anyOf' => [
				[ 'maxLength' => strlen( 'YYYY-MM' ) ],
				[ 'format' => 'date' ],
			],
		] );
	}

}
