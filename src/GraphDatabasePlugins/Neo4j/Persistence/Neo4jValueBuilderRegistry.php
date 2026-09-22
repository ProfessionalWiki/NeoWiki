<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use DateTimeImmutable;
use Laudis\Neo4j\Types\Date;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\PartialDate;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;

class Neo4jValueBuilderRegistry {

	private const string LATEST_DATE_SUFFIX = '_latest';

	/**
	 * @var array<string, callable(NeoValue): mixed>
	 */
	private array $builders = [];

	/**
	 * @var array<string, array<string, callable(NeoValue): mixed>>
	 */
	private array $companionBuilders = [];

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

	/**
	 * A companion builder adds a second node property next to the one a Statement's value is
	 * stored under, named after the Statement's Property plus the suffix.
	 *
	 * @param callable(NeoValue): mixed $builder
	 */
	private function registerCompanionBuilder( string $propertyTypeName, string $suffix, callable $builder ): void {
		$this->companionBuilders[$propertyTypeName][$suffix] = $builder;
	}

	/**
	 * @return array<string, mixed> Keys are the companion suffixes
	 */
	public function buildCompanionValues( string $propertyTypeName, NeoValue $value ): array {
		return array_map(
			static fn( callable $builder ): mixed => $builder( $value ),
			$this->companionBuilders[$propertyTypeName] ?? []
		);
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
		$registry->registerBuilder( 'monolingualText', self::buildMonolingualTextNeo4jValue( ... ) );
		$registry->registerCompanionBuilder( 'date', self::LATEST_DATE_SUFFIX, self::buildLatestDateNeo4jValue( ... ) );

		return $registry;
	}

	/**
	 * Neo4j has no language-tagged strings, so each part becomes `text@language`, split on the last `@`.
	 *
	 * @return string[]
	 */
	private static function buildMonolingualTextNeo4jValue( NeoValue $value ): array {
		if ( !$value instanceof MonolingualTextValue ) {
			return [];
		}

		return array_map(
			static fn( MonolingualText $part ): string => $part->text . '@' . $part->language,
			$value->parts
		);
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
	 * operations. A date of year or month precision is stored as its earliest day;
	 * the companion property holds its latest. Strings that are not ISO 8601 dates
	 * are omitted from the graph projection (the revision slot stays authoritative),
	 * which also keeps the stored list homogeneously typed.
	 *
	 * @return Date[]
	 */
	private static function buildDateNeo4jValue( NeoValue $value ): array {
		return self::buildParsedValues(
			$value,
			static fn( string $string ): ?Date => self::toNeo4jDate( PartialDate::tryParse( $string )?->earliest )
		);
	}

	/**
	 * Index-aligned with the list buildDateNeo4jValue() returns.
	 *
	 * @return Date[]
	 */
	private static function buildLatestDateNeo4jValue( NeoValue $value ): array {
		return self::buildParsedValues(
			$value,
			static fn( string $string ): ?Date => self::toNeo4jDate( PartialDate::tryParse( $string )?->latest )
		);
	}

	private static function toNeo4jDate( ?DateTimeImmutable $utcMidnight ): ?Date {
		return $utcMidnight === null ? null : new Date( intdiv( $utcMidnight->getTimestamp(), 86400 ) );
	}

	/**
	 * @template T of object
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
