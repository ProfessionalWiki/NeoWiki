<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelation;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;

readonly class StatementList {

	/**
	 * @var array<string, Statement> Key is property name
	 */
	private array $statements;

	/**
	 * @param Statement[] $statements
	 */
	public function __construct( array $statements = [] ) {
		$statementsByPropertyName = [];

		foreach ( $statements as $statement ) {
			if ( !( $statement instanceof Statement ) ) {
				throw new InvalidArgumentException( 'StatementList can only be constructed with Statement objects' );
			}
			$statementsByPropertyName[$statement->getPropertyName()->text] = $statement;
		}

		$this->statements = $statementsByPropertyName;
	}

	public function getTypedRelations( Schema $readerSchema ): TypedRelationList {
		/**
		 * @var TypedRelation[] $relations
		 */
		$relations = [];

		foreach ( $this->statements as $statement ) {
			if ( $statement->getPropertyType() === RelationType::NAME ) {
				/**
				 * @var RelationValue $value
				 */
				$value = $statement->getValue();

				if ( $readerSchema->hasProperty( $statement->getPropertyName() ) ) {
					/**
					 * @var RelationProperty $propertyDefinition
					 */
					$propertyDefinition = $readerSchema->getProperty( $statement->getPropertyName() );

					foreach ( $value->relations as $relation ) {
						$relations[] = new TypedRelation(
							id: $relation->id,
							targetId: $relation->targetId,
							properties: $relation->properties,
							type: $propertyDefinition->getRelationType()
						);
					}
				}
			}
		}

		return new TypedRelationList( $relations );
	}

	public function getReferencedSubjects(): SubjectIdList {
		$ids = [];

		foreach ( $this->getAllValues() as $value ) {
			if ( $value instanceof RelationValue ) {
				foreach ( $value->relations as $relation ) {
					$ids[] = $relation->targetId;
				}
			}
		}

		return new SubjectIdList( $ids );
	}

	/**
	 * @return NeoValue[]
	 */
	private function getAllValues(): array {
		return array_map(
			static function ( Statement $statement ): NeoValue {
				return $statement->getValue();
			},
			$this->statements
		);
	}

	/**
	 * @return array<string, Statement> Keys are property names
	 */
	public function asArray(): array {
		return $this->statements;
	}

	public function getStatement( PropertyName $property ): ?Statement {
		return $this->statements[$property->text] ?? null;
	}

}
