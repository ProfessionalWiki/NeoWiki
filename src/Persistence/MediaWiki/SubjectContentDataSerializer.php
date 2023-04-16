<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;

class SubjectContentDataSerializer {

	/**
	 * Mirrors @see SubjectContentDataDeserializer::deserialize
	 */
	public function serialize( PageSubjects $contentData ): string {
		$serializedSubjects = [];

		foreach ( $contentData->getAllSubjects()->asArray() as $subject ) {
			$serializedSubjects[$subject->id->text] = [
				'label' => $subject->label->text,
				'types' => $subject->types->toStringArray(),
				'relations' => $this->serializeRelations( $subject->relations ),
				'properties' => $subject->getProperties()->map,
			];
		}

		return json_encode(
			[
				'mainSubject' => $contentData->getMainSubject()?->id->text,
				'subjects' => (object)$serializedSubjects,
			],
			JSON_PRETTY_PRINT
		);
	}

	private function serializeRelations( RelationList $relations ): array {
		$serialized = [];

		foreach ( $relations->relations as $relation ) {
			$serialized[$relation->id->text] = [
				'type' => $relation->type->text,
				'target' => $relation->targetId->text,
				'properties' => $relation->properties->map
			];
		}

		return $serialized;
	}

}
