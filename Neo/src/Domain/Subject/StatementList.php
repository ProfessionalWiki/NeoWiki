<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

class StatementList {

	/**
	 * @var array<string, mixed>
	 */
	private readonly array $valuesByProperty;

	/**
	 * @param array<string, mixed> $map
	 */
	public function __construct( array $map ) {
		$this->valuesByProperty = $this->arrayFilter( $map );
	}

	/**
	 * @param array<array-key, mixed> $input
	 * @return array<string, mixed>
	 * @psalm-suppress all
	 */
	public function arrayFilter( array $input ): array {
		return array_filter( $input, function( $value ) {
			if ( is_array( $value ) ) {
				return count( $value ) > 0;
			}
			if ( is_string( $value ) ) {
				return $value !== '';
			}
			return true;
		} );
	}

	/**
	 * @param array<string, mixed> $patch Property name to list of new values
	 */
	public function applyPatch( array $patch ): self {
		$newMap = $this->valuesByProperty;

		foreach ( $patch as $propertyName => $values ) {
			if ( $values === null ) {
				unset( $newMap[$propertyName] );
			} else {
				$newMap[$propertyName] = $values;
			}
		}

		return new self( $newMap );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function asMap(): array {
		return $this->valuesByProperty;
	}

	public function getRelations( Schema $readerSchema ): RelationList {
		/**
		 * @var Relation[] $relations
		 */
		$relations = [];

		/**
		 * @var RelationProperty $propertyDefinition
		 */
		foreach ( $readerSchema->getRelationProperties()->asMap() as $propertyName => $propertyDefinition ) {
			foreach ( $this->getRelationValueArray( $propertyName ) as $relation ) {
				if ( $this->isValidRelation( $relation ) ) {
					$relations[] = $this->propertyValueToRelation( $relation, $propertyDefinition->getRelationType() );
				}
			}
		}

		return new RelationList( $relations );
	}

	private function getRelationValueArray( string $propertyName ): array {
		$value = $this->valuesByProperty[$propertyName] ?? [];

		if ( is_array( $value ) && !$this->isValidRelation( $value ) ) {
			return $value;
		}

		return [ $value ];
	}

	/**
	 * TODO: this construction can still fail despite earlier calls to isValidRelation.
	 * For instance via the checks in RelationId and SubjectId. Probably better
	 * to skip the checks and instead try this method in a try-catch.
	 */
	private function propertyValueToRelation( array $propertyValue, RelationType $type ): Relation {
		return new Relation(
			id: new RelationId( $propertyValue['id'] ),
			type: $type,
			targetId: new SubjectId( $propertyValue['target'] ),
			properties: new RelationProperties( $propertyValue['properties'] ?? [] ),
		);
	}

	private function isValidRelation( mixed $propertyValue ): bool {
		return is_array( $propertyValue )
			&& array_key_exists( 'target', $propertyValue ) && is_string( $propertyValue['target'] )
			&& array_key_exists( 'id', $propertyValue ) && is_string( $propertyValue['id'] );
	}

	public function withoutRelations( Schema $readerSchema ): self {
		$newMap = [];
		$relationProperties = $readerSchema->getRelationProperties();

		foreach ( $this->valuesByProperty as $propertyName => $value ) {
			if ( !$relationProperties->hasProperty( $propertyName )
				&& !$this->isValidRelation( $value ) // TODO: replace by writer-schema model
				&& !( is_array( $value ) && $this->isValidRelation( $value[0] ) ) ) {
				$newMap[$propertyName] = $value;
			}
		}

		return new self( $newMap );
	}

}
