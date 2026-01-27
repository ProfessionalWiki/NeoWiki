<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

readonly class StatementListPatcher {

	public function __construct(
		private PropertyTypeToValueType $propertyTypeToValueType,
		private IdGenerator $idGenerator,
	) {
	}

	/**
	 * The patch maps property name to scalar value representation (or null to delete the statement).
	 * This follows the JSON Merge Patch specification (RFC 7396).
	 *
	 * @param StatementList $statements
	 * @param array<string, mixed> $patch
	 */
	public function buildStatementList( StatementList $statements, array $patch ): StatementList {
		$newStatements = $statements->asArray();

		foreach ( $patch as $propertyName => $requestStatement ) {
			if ( is_array( $requestStatement ) && isset( $requestStatement['propertyType'] ) ) {
				/** @var string $propertyType TODO: handle missing propertyType */
				$propertyType = $requestStatement['propertyType'];
				$value = $this->deserializeValue( $propertyType, $requestStatement['value'] );

				if ( !$value->isEmpty() ) {
					$newStatements[$propertyName] = new Statement(
						property: new PropertyName( $propertyName ),
						propertyType: $propertyType,
						value: $value
					);

					continue;
				}
			}

			unset( $newStatements[$propertyName] );
		}

		return new StatementList( $newStatements );
	}

	private function deserializeValue( string $propertyType, mixed $value ): NeoValue {
		// TODO: validate value integrity
		return match ( $this->propertyTypeToValueType->lookup( $propertyType ) ) {
			ValueType::String => new StringValue( ...(array)$value ),
			ValueType::Number => new NumberValue( $value ),
			ValueType::Relation => $this->deserializeRelationValue( $value ),
			ValueType::Boolean => new BooleanValue( $value ),
		};
	}

	private function deserializeRelationValue( array $json ): RelationValue {
		$relations = [];

		foreach ( $json as $relation ) {
			if ( is_array( $relation ) ) { // TODO: complete validation and log warning on failure
				$relations[] = new Relation(
					id: $this->buildRelationId( $relation ),
					targetId: new SubjectId( $relation['target'] ), // TODO: handle exception
					properties: new RelationProperties( $relation['properties'] ?? [] )
				);
			}
		}

		return new RelationValue( ...$relations );
	}

	private function buildRelationId( array $relation ): RelationId {
		if ( array_key_exists( 'id', $relation ) ) {
			return new RelationId( $relation['id'] ); // TODO: handle exception
		}

		return RelationId::createNew( $this->idGenerator );
	}

}
