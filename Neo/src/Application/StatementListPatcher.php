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
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\FormatTypeLookup;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class StatementListPatcher {

	public function __construct(
		private readonly FormatTypeLookup $formatTypeLookup,
		private readonly GuidGenerator $guidGenerator,
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
			if ( $requestStatement !== null ) {
				$value = $this->deserializeValue( $requestStatement['format'], $requestStatement['value'] );

				if ( !$value->isEmpty() ) {
					$newStatements[$propertyName] = new Statement(
						property: new PropertyName( $propertyName ),
						format: $requestStatement['format'], // TODO: handle missing format
						value: $value
					);

					continue;
				}
			}

			unset( $newStatements[$propertyName] );
		}

		return new StatementList( $newStatements );
	}

	private function deserializeValue( string $format, mixed $value ): NeoValue {
		// TODO: validate value integrity
		return match ( $this->formatTypeLookup->formatToType( $format ) ) {
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

		return RelationId::createNew( $this->guidGenerator );
	}

}
