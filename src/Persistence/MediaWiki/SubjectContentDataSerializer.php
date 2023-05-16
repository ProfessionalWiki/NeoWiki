<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataSerializer {

	/**
	 * Mirrors @see SubjectContentDataDeserializer::deserialize
	 */
	public function serialize( PageSubjects $contentData ): string {
		return json_encode(
			[
				'mainSubject' => $contentData->getMainSubject()?->id->text,
				'subjects' => $this->serializeSubjects( $contentData->getAllSubjects() ),
			],
			JSON_PRETTY_PRINT
		);
	}

	private function serializeSubjects( SubjectMap $subjectMap ): object {
		$serializedSubjects = [];

		foreach ( $subjectMap->asArray() as $subject ) {
			$serializedSubjects[$subject->id->text] = [
				'label' => $subject->label->text,
				'schema' => $subject->getSchemaId()->getText(),
				'properties' => (object)array_merge(
					$subject->getProperties()->asMap(),
					$this->serializeRelations( $subject->getRelations() )
				),
			];
		}

		return (object)$serializedSubjects;
	}

	private function serializeRelations( RelationList $relations ): array {
		$serialized = [];

		foreach ( $relations->relations as $relation ) {
			$serialized[$relation->type->text][] = $this->serializeRelation( $relation );
		}

		return $serialized;
	}

	private function serializeRelation( Relation $relation ): array {
		$serialized = [];

		$serialized['target'] = $relation->targetId->text;

		if ( $relation->properties->map !== [] ) {
			$serialized['properties'] = (object)$relation->properties->map;
		}

		return $serialized;
	}

}
