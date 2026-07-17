<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

readonly class StatementListBuilder {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
		private IdGenerator $idGenerator,
		private SubjectIdParser $subjectIdParser,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 */
	public function build( array $statements ): StatementList {
		$built = [];

		foreach ( $statements as $propertyName => $entry ) {
			if ( !is_array( $entry ) || !isset( $entry['propertyType'] ) ) {
				continue;
			}

			$propertyType = $entry['propertyType'];
			$value = $this->deserializeValue( $propertyType, $entry['value'] );

			if ( $value->isEmpty() ) {
				continue;
			}

			$built[$propertyName] = new Statement(
				property: new PropertyName( $propertyName ),
				propertyType: $propertyType,
				value: $value
			);
		}

		return new StatementList( $built );
	}

	private function deserializeValue( string $propertyType, mixed $value ): NeoValue {
		$valueType = $this->propertyTypeLookup->getType( $propertyType )?->getValueType()
			?? ValueType::UnregisteredType;

		return match ( $valueType ) {
			ValueType::String => new StringValue( ...(array)$value ),
			ValueType::Number => new NumberValue( $value ),
			ValueType::Relation => $this->deserializeRelationValue( $value ),
			ValueType::Boolean => new BooleanValue( $value ),
			ValueType::UnregisteredType => new UnregisteredTypeValue( $propertyType, $value ),
		};
	}

	private function deserializeRelationValue( array $json ): RelationValue {
		$relations = [];

		foreach ( $json as $relation ) {
			if ( is_array( $relation ) ) {
				$relations[] = new Relation(
					id: $this->buildRelationId( $relation ),
					targetId: $this->subjectIdParser->parse( $relation['target'] ),
					properties: new RelationProperties( $relation['properties'] ?? [] )
				);
			}
		}

		return new RelationValue( ...$relations );
	}

	private function buildRelationId( array $relation ): RelationId {
		if ( array_key_exists( 'id', $relation ) ) {
			return new RelationId( $relation['id'] );
		}

		return RelationId::createNew( $this->idGenerator );
	}

}
