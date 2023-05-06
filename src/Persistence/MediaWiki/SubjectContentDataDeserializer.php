<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;

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

	private function newSubjectProperties( array $jsonArray, Schema $schema ): SubjectProperties {
		$propertyValueMap = [];

		foreach ( $jsonArray['properties'] ?? [] as $propertyName => $value ) {
			if ( $value !== null && !$schema->isRelationProperty( $propertyName ) ) { // TODO: deal with ArrayProperty?
				$propertyValueMap[$propertyName] = $value;
			}
		}

		return new SubjectProperties( $propertyValueMap );
	}

	private function newRelationList( array $jsonArray, Schema $schema ): RelationList {
		/**
		 * @var Relation[] $relations
		 */
		$relations = [];

		$properties = $jsonArray['properties'] ?? [];

		foreach ( $schema->getRelationProperties()->asMap() as $propertyName => $propertyDefinition ) {
			if ( array_key_exists( $propertyName, $properties ) ) {
				// TODO: this is probably not good because it will fail if the type gets changed
				// Likely we should always store all values as array, so we know how to deserialize them
				if ( $propertyDefinition->getType() === ValueType::Array ) {
					foreach ( $properties[$propertyName] as $value ) {
						$relations[] = $this->propertyValueToRelation( $propertyName, $value );
					}
				}
				else {
					$relations[] = $this->propertyValueToRelation( $propertyName, $properties[$propertyName] );
				}
			}
		}

		return new RelationList( $relations );
	}

	private function propertyValueToRelation( string $propertyName, array $propertyValue ): Relation {
		return new Relation(
			type: new RelationTypeId( $propertyName ),
			targetId: new SubjectId( $propertyValue['target'] ),
			properties: new RelationProperties( $propertyValue['properties'] ?? [] ),
		);
	}

}
