<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;

/**
 * Deserializes statements from persistence-type JSON.
 *
 * Note that the logic is similar, but not identical to, deserializing statements from the API.
 * For example, we need less validation and error handling, and don't need to generate IDs for new relations.
 */
class StatementDeserializer {

	public function __construct(
		private readonly PropertyTypeToValueType $propertyTypeToValueType
	) {
	}

	public function deserialize( string $propertyName, array $json ): Statement {
		return new Statement(
			property: new PropertyName( $propertyName ),
			propertyType: $json['type'],
			value: $this->deserializeValue( $json['type'], $json['value'] ),
		);
	}

	private function deserializeValue( string $propertyType, mixed $value ): NeoValue {
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
			$relations[] = new Relation(
				id: new RelationId( $relation['id'] ),
				targetId: new SubjectId( $relation['target'] ),
				properties: new RelationProperties( $relation['properties'] ?? [] )
			);
		}

		return new RelationValue( ...$relations );
	}

}
