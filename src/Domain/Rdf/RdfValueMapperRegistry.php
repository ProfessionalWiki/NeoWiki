<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;

/**
 * Maps a Statement's {@see NeoValue} to the RDF {@see RdfTerm}s that represent it — {@see Literal}s, or
 * {@see Iri}s for values that denote a resource (a url) — keyed by the Statement's Property Type. The
 * seam mirrors Neo4jValueBuilderRegistry so extension-defined property types can contribute their RDF
 * representation via NeoWikiRegistrar::addRdfValueMapper.
 *
 * Relation values are not mapped here; the projector reifies them separately (two-layer approach).
 * A Property Type without a mapper (including an unregistered type) yields null and is skipped,
 * matching the Neo4j projection's graceful degradation.
 */
class RdfValueMapperRegistry {

	/**
	 * @var array<string, callable(NeoValue): RdfTerm[]>
	 */
	private array $mappers = [];

	/**
	 * @param callable(NeoValue): RdfTerm[] $mapper
	 */
	public function registerMapper( string $propertyTypeName, callable $mapper ): void {
		$this->mappers[$propertyTypeName] = $mapper;
	}

	/**
	 * @return RdfTerm[]|null null when no mapper is registered for the Property Type.
	 */
	public function mapValue( string $propertyTypeName, NeoValue $value ): ?array {
		if ( !array_key_exists( $propertyTypeName, $this->mappers ) ) {
			return null;
		}

		return ( $this->mappers[$propertyTypeName] )( $value );
	}

	public function hasMapper( string $propertyTypeName ): bool {
		return array_key_exists( $propertyTypeName, $this->mappers );
	}

	public static function withCoreMappers(): self {
		$registry = new self();

		$registry->registerMapper( TextType::NAME, self::stringMapper( 'string' ) );
		$registry->registerMapper( SelectType::NAME, self::stringMapper( 'string' ) );
		$registry->registerMapper( UrlType::NAME, self::urlMapper( ... ) );
		$registry->registerMapper( NumberType::NAME, self::mapNumber( ... ) );
		$registry->registerMapper( BooleanType::NAME, self::mapBoolean( ... ) );
		$registry->registerMapper( DateType::NAME, self::mapDate( ... ) );
		$registry->registerMapper( DateTimeType::NAME, self::mapDateTime( ... ) );

		return $registry;
	}

	/**
	 * @return callable(NeoValue): Literal[]
	 */
	private static function stringMapper( string $xsdType ): callable {
		return static function ( NeoValue $value ) use ( $xsdType ): array {
			$scalars = $value->toScalars();

			if ( !is_array( $scalars ) ) {
				return [];
			}

			$literals = [];

			foreach ( $scalars as $part ) {
				if ( is_scalar( $part ) ) {
					$literals[] = RdfLiteralFactory::typed( (string)$part, $xsdType );
				}
			}

			return $literals;
		};
	}

	/**
	 * Maps a url value: each part becomes an IRI object when it is a valid absolute IRI, so the statement
	 * joins the graph as a resource that SPARQL can follow rather than an inert string. A part that is not
	 * a valid absolute IRI (e.g. the scheme-less values the url Property Type still accepts) keeps the
	 * xsd:anyURI literal form, so nothing is lost. Reuses {@see Iri::isSafeAbsolute}, the same IRI-safety
	 * check the Mapping layer applies to authored terms.
	 *
	 * @return RdfTerm[]
	 */
	private static function urlMapper( NeoValue $value ): array {
		$scalars = $value->toScalars();

		if ( !is_array( $scalars ) ) {
			return [];
		}

		$terms = [];

		foreach ( $scalars as $part ) {
			if ( is_scalar( $part ) ) {
				$string = (string)$part;
				$terms[] = Iri::isSafeAbsolute( $string )
					? new Iri( $string )
					: RdfLiteralFactory::typed( $string, 'anyURI' );
			}
		}

		return $terms;
	}

	/**
	 * @return Literal[]
	 */
	private static function mapNumber( NeoValue $value ): array {
		$number = $value->toScalars();

		if ( !is_int( $number ) && !is_float( $number ) ) {
			return [];
		}

		$literal = RdfLiteralFactory::number( $number );

		return $literal === null ? [] : [ $literal ];
	}

	/**
	 * @return Literal[]
	 */
	private static function mapBoolean( NeoValue $value ): array {
		$boolean = $value->toScalars();

		if ( !is_bool( $boolean ) ) {
			return [];
		}

		return [ RdfLiteralFactory::boolean( $boolean ) ];
	}

	/**
	 * @return Literal[]
	 */
	private static function mapDate( NeoValue $value ): array {
		return self::mapValidatedStrings(
			$value,
			'date',
			static fn( string $string ): bool => DateProperty::parseStrictDate( $string ) !== null
		);
	}

	/**
	 * @return Literal[]
	 */
	private static function mapDateTime( NeoValue $value ): array {
		return self::mapValidatedStrings(
			$value,
			'dateTime',
			static fn( string $string ): bool => DateTimeProperty::parseStrictDateTime( $string ) !== null
		);
	}

	/**
	 * Keeps only the parts that are valid lexical forms for the given xsd datatype, so the projection
	 * stays well-typed. Invalid parts are dropped, as the Neo4j projection drops unparseable dateTimes.
	 *
	 * @param callable(string): bool $isValid
	 * @return Literal[]
	 */
	private static function mapValidatedStrings( NeoValue $value, string $xsdType, callable $isValid ): array {
		$scalars = $value->toScalars();

		if ( !is_array( $scalars ) ) {
			return [];
		}

		$literals = [];

		foreach ( $scalars as $part ) {
			if ( is_string( $part ) && $isValid( $part ) ) {
				$literals[] = RdfLiteralFactory::typed( $part, $xsdType );
			}
		}

		return $literals;
	}

}
