<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataDeserializer {

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
		return new Subject(
			id: new SubjectId( $id ),
			label: new SubjectLabel( $jsonArray['label'] ),
			schemaId: new SchemaId( $jsonArray['schema'] ),
			statements: $this->newSubjectProperties( $jsonArray ),
		);
	}

	private function newSubjectProperties( array $jsonArray ): StatementList {
		$propertyValueMap = [];

		foreach ( $jsonArray['properties'] ?? [] as $propertyName => $value ) {
			if ( $value !== null ) { // TODO: deal with multiple values?
				$propertyValueMap[$propertyName] = $value;
			}
		}

		return new StatementList( $propertyValueMap );
	}

}
