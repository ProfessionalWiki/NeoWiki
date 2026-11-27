<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class DateProperty extends PropertyDefinition {

	private const string EXPECTED = 'a date in the form YYYY, YYYY-MM or YYYY-MM-DD';

	private const string EXPECTED_MIN_PRECISION = '"year", "month" or "day"';

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

		parent::__construct( $core );
	}

	private static function ensureValidDateOrNull( string $field, ?string $value ): void {
		if ( $value === null ) {
			return;
		}

		if ( PartialDate::tryParse( $value ) === null ) {
			throw InvalidFieldException::mustBe( $field, self::EXPECTED, $value );
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

	public function getMinPrecision(): DatePrecision {
		return $this->minPrecision ?? DatePrecision::Day;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			minimum: self::stringOrNull( $property, 'minimum', self::EXPECTED ),
			maximum: self::stringOrNull( $property, 'maximum', self::EXPECTED ),
			minPrecision: self::minPrecisionFromJson( self::stringOrNull( $property, 'minPrecision', self::EXPECTED_MIN_PRECISION ) ),
		);
	}

	private static function minPrecisionFromJson( ?string $minPrecision ): ?DatePrecision {
		if ( $minPrecision === null ) {
			return null;
		}

		return DatePrecision::tryFrom( $minPrecision )
			?? throw InvalidFieldException::mustBe( 'minPrecision', self::EXPECTED_MIN_PRECISION, $minPrecision );
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
			'pattern' => PartialDate::patternOfAtLeast( $this->getMinPrecision() ),
			'anyOf' => [
				[ 'maxLength' => strlen( 'YYYY-MM' ) ],
				[ 'format' => 'date' ],
			],
		] );
	}

}
