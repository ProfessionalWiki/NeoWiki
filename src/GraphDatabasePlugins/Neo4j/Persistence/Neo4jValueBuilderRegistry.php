<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use DateTimeImmutable;
use Laudis\Neo4j\Types\Date;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;

class Neo4jValueBuilderRegistry {

	/**
	 * @var array<string, callable(NeoValue): mixed>
	 */
	private array $builders = [];

	/**
	 * @param callable(NeoValue): mixed $builder
	 */
	public function registerBuilder( string $propertyTypeName, callable $builder ): void {
		$this->builders[$propertyTypeName] = $builder;
	}

	public function buildNeo4jValue( string $propertyTypeName, NeoValue $value ): mixed {
		if ( !array_key_exists( $propertyTypeName, $this->builders ) ) {
			return null;
		}

		return $this->builders[$propertyTypeName]( $value );
	}

	public function hasBuilder( string $propertyTypeName ): bool {
		return array_key_exists( $propertyTypeName, $this->builders );
	}

	public static function withCoreBuilders(): self {
		$registry = new self();

		$toScalars = static fn( NeoValue $value ): mixed => $value->toScalars();

		$registry->registerBuilder( 'text', $toScalars );
		$registry->registerBuilder( 'url', $toScalars );
		$registry->registerBuilder( 'number', $toScalars );
		$registry->registerBuilder( 'select', $toScalars );
		$registry->registerBuilder( 'boolean', $toScalars );
		$registry->registerBuilder( 'dateTime', self::buildDateTimeNeo4jValue( ... ) );
		$registry->registerBuilder( 'date', self::buildDateNeo4jValue( ... ) );

		return $registry;
	}

	/**
	 * The Neo4j driver persists DateTimeImmutable as a native Neo4j datetime, so the
	 * stored values work with Cypher temporal operations. Strings that are not strict
	 * ISO 8601 datetimes are omitted from the graph projection (the revision slot stays
	 * authoritative), which also keeps the stored list homogeneously typed.
	 *
	 * @return DateTimeImmutable[]
	 */
	private static function buildDateTimeNeo4jValue( NeoValue $value ): array {
		return self::buildParsedValues( $value, DateTimeProperty::parseStrictDateTime( ... ) );
	}

	/**
	 * The Laudis Date type serializes to a native Neo4j date (a DateTimeImmutable
	 * would become a datetime), so the stored values work with Cypher temporal
	 * operations. Strings that are not strict ISO 8601 dates are omitted from the
	 * graph projection (the revision slot stays authoritative), which also keeps
	 * the stored list homogeneously typed.
	 *
	 * @return Date[]
	 */
	private static function buildDateNeo4jValue( NeoValue $value ): array {
		return self::buildParsedValues( $value, self::parseNeo4jDate( ... ) );
	}

	private static function parseNeo4jDate( string $value ): ?Date {
		$date = DateProperty::parseStrictDate( $value );

		return $date === null ? null : new Date( self::daysSinceUnixEpoch( $date ) );
	}

	private static function daysSinceUnixEpoch( DateTimeImmutable $utcMidnight ): int {
		return intdiv( $utcMidnight->getTimestamp(), 86400 );
	}

	/**
	 * @phan-template T of object
	 * @param callable(string): ?T $parse
	 * @return T[]
	 */
	private static function buildParsedValues( NeoValue $value, callable $parse ): array {
		$scalars = $value->toScalars();

		if ( !is_array( $scalars ) ) {
			return [];
		}

		$values = [];

		foreach ( $scalars as $string ) {
			$parsed = is_string( $string ) ? $parse( $string ) : null;

			if ( $parsed !== null ) {
				$values[] = $parsed;
			}
		}

		return $values;
	}

}
