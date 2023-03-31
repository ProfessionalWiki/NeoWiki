<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeIdList;

class SubjectSlotDeserializer {

	/**
	 * Mirrors @see SubjectSlotSerializer::serialize
	 */
	public function deserialize( string $json ): SubjectMap {
		$jsonArray = json_decode( $json, true )['subjects'] ?? [];

		return new SubjectMap(
			...array_map(
				fn( string $id, array $subject ) => $this->deserializeSubject( $id, $subject ),
				array_keys( $jsonArray ),
				$jsonArray,
			)
		);
	}

	private function deserializeSubject( string $id, array $jsonArray ): Subject {
		return new Subject(
			id: new SubjectId( $id ),
			label: new SubjectLabel( $jsonArray['label'] ),
			types: $this->newSubjectTypeIdList( $jsonArray ),
			relations: $this->newRelationList( $jsonArray ),
			properties: $this->newSubjectProperties( $jsonArray ),
		);
	}

	private function newSubjectTypeIdList( array $jsonArray ): SubjectTypeIdList {
		return new SubjectTypeIdList(
			array_map(
				fn( string $id ) => new SubjectTypeId( $id ),
				$jsonArray['types'] ?? []
			)
		);
	}

	private function newRelationList( array $jsonArray ): RelationList {
		$relations = (array)( $jsonArray['relations'] ?? [] );

		return new RelationList(
			array_map(
				fn( string $id, array $relation ) => new Relation(
					id: new RelationId( $id ),
					type: new RelationTypeId( $relation['type'] ),
					targetId: new SubjectId( $relation['target'] ),
					properties: new RelationProperties( $relation['properties'] ?? [] ),
				),
				array_keys( $relations ),
				$relations
			)
		);
	}

	private function newSubjectProperties( array $jsonArray ): SubjectProperties {
		return new SubjectProperties( $jsonArray['properties'] ?? [] );
	}

}
