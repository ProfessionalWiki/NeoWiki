<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
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
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use TypeError;

readonly class StatementListBuilder {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
		private IdGenerator $idGenerator,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @throws InvalidArgumentException When a value does not fit the property type it declares.
	 */
	public function build( array $statements ): StatementList {
		$built = [];

		foreach ( $statements as $propertyName => $entry ) {
			if ( !is_array( $entry ) || !isset( $entry['propertyType'] ) ) {
				continue;
			}

			$propertyType = $entry['propertyType'];

			if ( !is_string( $propertyType ) ) {
				throw new InvalidArgumentException( "Property type of \"{$propertyName}\" must be a string" );
			}

			$value = $this->deserializeValue( (string)$propertyName, $propertyType, $entry['value'] ?? null );

			if ( $value->isEmpty() ) {
				continue;
			}

			$built[$propertyName] = new Statement(
				property: new PropertyName( (string)$propertyName ),
				propertyType: $propertyType,
				value: $value
			);
		}

		return new StatementList( $built );
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function deserializeValue( string $propertyName, string $propertyType, mixed $value ): NeoValue {
		$valueType = $this->propertyTypeLookup->getType( $propertyType )?->getValueType()
			?? ValueType::UnregisteredType;

		// A JSON object reaches the list-shaped types as named arguments rather than as a type
		// error: it would be stored as an object where every reader expects a list, and the graph
		// projection cannot hold one. The value types cannot tell it apart from a list, so it is
		// rejected before they see it.
		if ( in_array( $valueType, [ ValueType::String, ValueType::Relation ], true )
			&& is_array( $value ) && !array_is_list( $value ) ) {
			throw new InvalidArgumentException(
				"Value of \"{$propertyName}\" must be a list, not an object"
			);
		}

		// The value types below declare what each shape accepts, so a value of the wrong shape
		// arrives here as a TypeError. Every one of them comes from the caller's value, never from
		// internal state, so they are reported as bad input rather than escaping as a server error.
		try {
			return match ( $valueType ) {
				ValueType::String => new StringValue( ...(array)$value ),
				ValueType::Number => new NumberValue( $value ),
				ValueType::Relation => $this->deserializeRelationValue( $value ),
				ValueType::Boolean => new BooleanValue( $value ),
				ValueType::UnregisteredType => new UnregisteredTypeValue( $propertyType, $value ),
			};
		} catch ( TypeError $e ) {
			throw new InvalidArgumentException(
				"Value of \"{$propertyName}\" does not fit property type \"{$propertyType}\"",
				0,
				$e
			);
		}
	}

	private function deserializeRelationValue( array $json ): RelationValue {
		$relations = [];

		foreach ( $json as $relation ) {
			// Skipping an entry that is not a relation object would turn a list of bare target
			// ids into an empty value, which the write paths read as the Statement being absent.
			// Raised as a TypeError so it reaches the caller as the same bad-value report the
			// constructors below produce for every other misshapen relation.
			if ( !is_array( $relation ) ) {
				throw new TypeError( 'Relation entry must be an object' );
			}

			$relations[] = new Relation(
				id: $this->buildRelationId( $relation ),
				targetId: new SubjectId( $relation['target'] ?? null ),
				properties: new RelationProperties( $relation['properties'] ?? [] )
			);
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
