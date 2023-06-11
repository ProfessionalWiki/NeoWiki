<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;

class SubjectContentDataDeserializer {

	public function __construct(
		private readonly SchemaRepository $schemaRepository,
	) {
	}

	/**
	 * Mirrors @see SubjectContentDataSerializer::serialize
	 */
	public function deserialize( string $json ): PageSubjects {
		$jsonArray = json_decode( $json, true );
		$subjects = $this->deserializeSubjects( $jsonArray );

		if ( ( $jsonArray['mainSubject'] ?? null ) === null ) {
			return new PageSubjects(
				null,
				$subjects,
			);
		}

		$mainSubjectId = new SubjectId( $jsonArray['mainSubject'] );

		return new PageSubjects(
			$subjects->getSubject( $mainSubjectId ),
			$subjects->without( $mainSubjectId ),
		);
	}

	private function deserializeSubjects( array $jsonArray ): SubjectMap {
		$subjectsArray = $jsonArray['subjects'] ?? [];

		return new SubjectMap(
			...array_map(
				fn( string $id, array $subject ) => $this->deserializeSubject( $id, $subject ),
				array_keys( $subjectsArray ),
				$subjectsArray,
			)
		);
	}

	private function deserializeSubject( string $id, array $jsonArray ): Subject {
		$schemaId = new SchemaId( $jsonArray['schema'] );
		$schema = $this->schemaRepository->getSchema( $schemaId );

		// TODO: is this the right approach?
		$schema ??= new Schema( $schemaId, '', new PropertyDefinitions( [] ) );

		return new Subject(
			id: new SubjectId( $id ),
			label: new SubjectLabel( $jsonArray['label'] ),
			schemaId: $schemaId,
			properties: $this->newSubjectProperties( $jsonArray, $schema ),
			relations: $this->newRelationList( $jsonArray, $schema ),
		);
	}

	private function newSubjectProperties( array $jsonArray, Schema $schema ): StatementList {
		$propertyValueMap = [];

		foreach ( $jsonArray['properties'] ?? [] as $propertyName => $value ) {
			if ( $value !== null && !$schema->isRelationProperty( $propertyName ) ) { // TODO: deal with ArrayProperty?
				$propertyValueMap[$propertyName] = $value;
			}
		}

		return new StatementList( $propertyValueMap );
	}

	private function newRelationList( array $jsonArray, Schema $schema ): RelationList {
		/**
		 * @var Relation[] $relations
		 */
		$relations = [];

		$properties = $jsonArray['properties'] ?? [];

		foreach ( $schema->getRelationProperties()->asMap() as $propertyName => $propertyDefinition ) {
			if ( $propertyDefinition->getType() === ValueType::Relation && array_key_exists( $propertyName, $properties ) ) {
				$value = $properties[$propertyName];

				if ( $this->isValidRelation( $value ) ) {
					$relations[] = $this->propertyValueToRelation( $propertyName, $value );
				}
				if ( is_array( $value ) ) {
					foreach ( $value as $relation ) {
						if ( $this->isValidRelation( $relation ) ) {
							$relations[] = $this->propertyValueToRelation( $propertyName, $relation );
						}
					}
				}
			}
		}

		return new RelationList( $relations );
	}

	private function propertyValueToRelation( string $propertyName, array $propertyValue ): Relation {
		return new Relation(
			type: new RelationType( $propertyName ),
			targetId: new SubjectId( $propertyValue['target'] ),
			properties: new RelationProperties( $propertyValue['properties'] ?? [] ),
		);
	}

	private function isValidRelation( mixed $propertyValue ): bool {
		return is_array( $propertyValue ) && array_key_exists( 'target', $propertyValue );
	}

}
