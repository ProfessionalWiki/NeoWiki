<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Scribunto;

use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use RuntimeException;

class CypherResultConverter {

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
			return $this->convertAssociative( $value->toArray() );
		}

		throw new RuntimeException(
			sprintf( 'Unsupported Cypher value type: %s', get_debug_type( $value ) )
		);
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

	private function convertAssociative( iterable $entries ): array {
		$result = [];
		foreach ( $entries as $key => $value ) {
			$result[$key] = $this->convert( $value );
		}
		return $result;
	}

}
