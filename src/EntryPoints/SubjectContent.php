<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use ProfessionalWiki\NeoWiki\Domain\Relation;
use ProfessionalWiki\NeoWiki\Domain\RelationId;
use ProfessionalWiki\NeoWiki\Domain\RelationList;
use ProfessionalWiki\NeoWiki\Domain\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\SubjectTypeId;
use ProfessionalWiki\NeoWiki\Domain\SubjectTypeIdList;
use Status;
use stdClass;

class SubjectContent extends \JsonContent {

	public const CONTENT_MODEL_ID = 'NeoWikiSubject';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

	public function getData(): Status {
		$status = parent::getData();

		if ( $status->isGood() && !$this->isSubjectJson( $status->getValue() ) ) {
			return Status::newFatal( 'Invalid NeoWiki Subject' );
		}

		return $status;
	}

	private function isSubjectJson( stdClass $json ): bool {
		return true; // TODO: validate format
	}

	public function getSubject(): Subject {
		$jsonArray = (array)$this->getData()->getValue();

		return new Subject(
			id: new SubjectId( $jsonArray['id'] ),
			types: $this->newSubjectTypeIdList( $jsonArray ),
			relations: $this->newRelationList( $jsonArray ),
			properties: $this->newSubjectProperties( $jsonArray ),
		);
	}

	private function newSubjectTypeIdList( array $jsonArray ): SubjectTypeIdList {
		return new SubjectTypeIdList(
			array_map(
				fn( string $id ) => new SubjectTypeId( $id ),
				(array)( $jsonArray['types'] ?? [] )
			)
		);
	}

	private function newRelationList( array $jsonArray ): RelationList {
		$relations = (array)( $jsonArray['relations'] ?? [] );

		return new RelationList(
			array_map(
				fn( string $id, stdClass $relation ) => new Relation(
					id: new RelationId( $id ),
					type: new RelationTypeId( $relation->type ),
					targetId: new SubjectId( $relation->target ),
					properties: new RelationProperties( (array)( ( (array)$relation )['properties'] ?? [] ) ),
				),
				array_keys( $relations ),
				$relations
			)
		);
	}

	private function newSubjectProperties( array $jsonArray ): SubjectProperties {
		return new SubjectProperties( (array)( $jsonArray['properties'] ?? [] ) );
	}

}
