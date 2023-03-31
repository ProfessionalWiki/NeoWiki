<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectSlotSerializer {

	/**
	 * Mirrors @see SubjectSlotDeserializer::deserialize
	 */
	public function serialize( SubjectMap $subjects ): string {
		$serializedSubjects = [];

		foreach ( $subjects->asArray() as $subject ) {
			$serializedSubjects[$subject->id->text] = [
				'label' => $subject->label->text,
				'types' => $subject->types->toStringArray(),
				'relations' => $this->serializeRelations( $subject->relations ),
				'properties' => $subject->getProperties()->map,
			];
		}

		return json_encode( [ 'subjects' => (object)$serializedSubjects ], JSON_PRETTY_PRINT );
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
