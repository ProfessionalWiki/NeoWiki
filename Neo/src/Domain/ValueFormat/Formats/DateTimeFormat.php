<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use DateTimeImmutable;
use DateTimeZone;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\LocalDateTime;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class DateTimeFormat implements ValueFormat {

	public const NAME = 'dateTime';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): DateTimeProperty {
		return DateTimeProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		$times = [];

		if ( $value instanceof StringValue ) {
			foreach ( $value->strings as $string ) {
				try {
					$times[] = $this->dateTimeStringToNeo4jDateTime( $string );
				}
				catch ( \Exception ) {
					// Ignore invalid dates
				}
			}
		}

		return new CypherList( $times );
	}

	private function dateTimeStringToNeo4jDateTime( string $dateTimeString ): LocalDateTime {
		$dateTime = new DateTimeImmutable( $dateTimeString, new DateTimeZone( 'UTC' ) );

		return new LocalDateTime(
			$dateTime->getTimestamp(),
			(int)substr( $dateTimeString, 20, 3 ) * 1000000
		);
	}

}
