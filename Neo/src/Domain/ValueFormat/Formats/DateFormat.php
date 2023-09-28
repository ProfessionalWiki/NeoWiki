<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use DateTimeImmutable;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\Date;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class DateFormat implements ValueFormat {

	public const NAME = 'date';
	private const SECONDS_PER_DAY = 86400;

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): DateProperty {
		return DateProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		$dates = [];

		if ( $value instanceof StringValue ) {
			foreach ( $value->strings as $string ) {
				try {
					$dates[] = $this->dateStringToNeo4jDate( $string );
				}
				catch ( \Exception ) {
					// Ignore invalid dates
				}
			}
		}

		return new CypherList( $dates );
	}

	private function dateStringToNeo4jDate( string $date ): Date {
		return new Date(
			days: intdiv(
				( new DateTimeImmutable( $date ) )->getTimestamp(),
				self::SECONDS_PER_DAY
			)
		);
	}

}
