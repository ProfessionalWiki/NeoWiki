<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use RuntimeException;

class Neo4jResultNormalizer {

	public function convertRows( CypherList $rows ): array {
		return $this->convertList( $rows );
	}

	private function convert( mixed $value ): mixed {
		if ( is_scalar( $value ) || $value === null ) {
			return $value;
		}

		if ( $value instanceof CypherList ) {
			return $this->convertList( $value );
		}

		if ( $value instanceof CypherMap ) {
			return $this->convertAssociative( $value );
		}

		if ( $value instanceof Node ) {
			return [
				'id' => $value->getId(),
				'labels' => $this->convertList( $value->getLabels() ),
				'properties' => $this->convertAssociative( $value->getProperties() ),
			];
		}

		if ( $value instanceof Relationship ) {
			return [
				'id' => $value->getId(),
				'type' => $value->getType(),
				'startNodeId' => $value->getStartNodeId(),
				'endNodeId' => $value->getEndNodeId(),
				'properties' => $this->convertAssociative( $value->getProperties() ),
			];
		}

		if ( $value instanceof UnboundRelationship ) {
			return [
				'id' => $value->getId(),
				'type' => $value->getType(),
				'properties' => $this->convertAssociative( $value->getProperties() ),
			];
		}

		if ( $value instanceof Path ) {
			return [
				'nodes' => $this->convertList( $value->getNodes() ),
				'relationships' => $this->convertList( $value->getRelationships() ),
			];
		}

		if ( $value instanceof AbstractCypherObject ) {
			return $this->convertPropertyObject( $value );
		}

		throw new RuntimeException(
			sprintf( 'Unsupported Cypher value type: %s', get_debug_type( $value ) )
		);
	}

	private function convertPropertyObject( AbstractCypherObject $value ): string|array {
		if ( $value instanceof DateTime ) {
			return $this->offsetDateTimeToIso( $value );
		}

		if ( $value instanceof DateTimeZoneId ) {
			return $this->zoneIdDateTimeToIso( $value );
		}

		if ( $value instanceof LocalDateTime ) {
			return $value->toDateTime()->format( 'Y-m-d\TH:i:s' )
				. $this->fractionalSeconds( $value->getNanoseconds() );
		}

		if ( $value instanceof Date ) {
			return $value->toDateTime()->format( 'Y-m-d' );
		}

		return $this->convertAssociative( $value->toArray() );
	}

	private function offsetDateTimeToIso( DateTime $value ): string {
		$offset = $this->offsetFromSeconds( $value->getTimeZoneOffsetSeconds() );

		return ( new DateTimeImmutable( '@' . $value->getSeconds() ) )
			->setTimezone( new DateTimeZone( $offset ) )
			->format( 'Y-m-d\TH:i:s' )
			. $this->fractionalSeconds( $value->getNanoseconds() )
			. $offset;
	}

	private function zoneIdDateTimeToIso( DateTimeZoneId $value ): string {
		$dateTime = $value->toDateTime();

		return $dateTime->format( 'Y-m-d\TH:i:s' )
			. $this->fractionalSeconds( $value->getNanoseconds() )
			. $dateTime->format( 'P' );
	}

	private function offsetFromSeconds( int $offsetSeconds ): string {
		$sign = $offsetSeconds < 0 ? '-' : '+';
		$absoluteSeconds = abs( $offsetSeconds );

		return sprintf( '%s%02d:%02d', $sign, intdiv( $absoluteSeconds, 3600 ), intdiv( $absoluteSeconds % 3600, 60 ) );
	}

	private function fractionalSeconds( int $nanoseconds ): string {
		if ( $nanoseconds === 0 ) {
			return '';
		}

		return rtrim( sprintf( '.%09d', $nanoseconds ), '0' );
	}

	private function convertList( CypherList $list ): array {
		$values = [];
		$index = 1;
		foreach ( $list as $value ) {
			$values[$index] = $this->convert( $value );
			$index++;
		}
		return $values;
	}

	/**
	 * @param iterable<string|int, mixed> $entries
	 */
	private function convertAssociative( iterable $entries ): array {
		$result = [];
		foreach ( $entries as $key => $value ) {
			$result[$key] = $this->convert( $value );
		}
		return $result;
	}

}
