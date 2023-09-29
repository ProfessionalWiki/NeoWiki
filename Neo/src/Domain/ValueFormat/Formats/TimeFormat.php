<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\LocalTime;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class TimeFormat implements ValueFormat {

	public const NAME = 'time';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): TimeProperty {
		return TimeProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		$times = [];

		if ( $value instanceof StringValue ) {
			foreach ( $value->strings as $string ) {
				if ( $this->isValidTime( $string ) ) {
					$times[] = $this->timeStringToNeo4jTime( $string );
				}
			}
		}

		return new CypherList( $times );
	}

	private function isValidTime( string $time ): bool {
		return preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $time ) === 1;
	}

	private function timeStringToNeo4jTime( string $time ): LocalTime {
		$segments = explode( ':', $time );
		$hours = (int)$segments[0];
		$minutes = (int)( $segments[1] ?? 0 );
		$seconds = (int)( $segments[2] ?? 0 );

		$nanoseconds = ( $hours * 3600 + $minutes * 60 + $seconds ) * 1_000_000_000;

		return new LocalTime( $nanoseconds );
	}

}
